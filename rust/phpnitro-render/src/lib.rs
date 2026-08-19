//! FFI entry points for the shared PhpNitro rendering core.
//!
//! Everything reachable from other languages (Kotlin/JNI, Swift, Python
//! ctypes, C# P/Invoke) lives here as thin `extern "C"` wrappers; the real
//! logic lives in the sibling modules. See `include/phpnitro_render.h` for
//! the hand-written C header consumers actually bind against.
//!
//! ## Ownership
//!
//! Rust allocates and frees every handle it hands out — a caller never
//! `free()`s a returned pointer itself, only ever passes it back to the
//! matching `phpnitro_render_free_*` function. This matters more than
//! usual here since four different languages (Kotlin/JNI, Swift, Python
//! ctypes, C# P/Invoke) will eventually call into the same library.
//!
//! ## Panic safety
//!
//! A Rust panic unwinding across an `extern "C"` boundary is undefined
//! behavior. Every entry point that can realistically panic (malformed
//! JSON reaching an `unwrap()` somewhere downstream, etc.) is wrapped in
//! `catch_unwind` and converts a caught panic into a null return plus a
//! `phpnitro_render_last_error()` message instead.
//!
//! ## What's NOT in this v1 surface
//!
//! `phpnitro_render_frame` deliberately has no `interaction_state_json`
//! parameter — `clientPanel`/`hScroll`/`vScroll` now paint (see
//! `raster.rs`), but always at their server-authored resting state (the
//! `initiallyActive` panel, an unscrolled viewport) since no client-side
//! tab-switch/drag offset reaches this render call yet — the same
//! interaction-state plumbing `hittest.rs` already has for hit-testing,
//! just not threaded into the render path yet. Crossfade/hero transitions
//! (needing a previous-frame + progress fraction) are likewise out of
//! this surface for now: only Android has anything to preserve there, so
//! deferring is zero regression for every other platform.

use std::cell::RefCell;
use std::ffi::{CStr, CString};
use std::os::raw::c_char;

pub mod animate;
pub mod charts;
pub mod hittest;
pub mod protocol;
pub mod raster;
pub mod text;

#[cfg(target_os = "android")]
pub mod jni_bridge;

use hittest::InteractionState;
use text::TextRenderer;
use tiny_skia::Pixmap;

thread_local! {
    static LAST_ERROR: RefCell<Option<CString>> = const { RefCell::new(None) };
}

fn set_last_error(message: impl Into<Vec<u8>>) {
    let c_string = CString::new(message).unwrap_or_else(|_| {
        CString::new("phpnitro-render: error message itself contained a NUL byte").unwrap()
    });
    LAST_ERROR.with(|cell| *cell.borrow_mut() = Some(c_string));
}

/// # Safety
/// `ptr` must be either null or a valid pointer to a NUL-terminated,
/// UTF-8 C string that outlives this call.
unsafe fn borrow_str<'a>(ptr: *const c_char) -> Option<&'a str> {
    if ptr.is_null() {
        return None;
    }
    CStr::from_ptr(ptr).to_str().ok()
}

fn interaction_state_from_json(json: Option<&str>) -> InteractionState {
    match json {
        Some(s) if !s.trim().is_empty() => serde_json::from_str(s).unwrap_or_default(),
        _ => InteractionState::default(),
    }
}

/// Returns the crate's own version as a NUL-terminated C string, valid for
/// the lifetime of the program (leaked once, not per-call).
#[no_mangle]
pub extern "C" fn phpnitro_render_version() -> *const c_char {
    static VERSION: std::sync::OnceLock<CString> = std::sync::OnceLock::new();
    VERSION
        .get_or_init(|| CString::new(env!("CARGO_PKG_VERSION")).expect("version has no NUL bytes"))
        .as_ptr()
}

/// Last error set by this thread's most recent call into this library, or
/// null if the last call didn't fail. The returned pointer is borrowed —
/// valid until the next call into this library on the same thread, never
/// to be freed by the caller.
#[no_mangle]
pub extern "C" fn phpnitro_render_last_error() -> *const c_char {
    LAST_ERROR.with(|cell| cell.borrow().as_ref().map_or(std::ptr::null(), |s| s.as_ptr()))
}

/// Opaque handle owning the loaded fonts (`text.rs`'s `TextRenderer`).
/// Font loading/shaping setup isn't free — create ONE of these per app
/// lifetime (or per screen at most), never one per frame.
pub struct PhpNitroRenderer {
    text_renderer: TextRenderer,
}

#[no_mangle]
pub extern "C" fn phpnitro_render_new() -> *mut PhpNitroRenderer {
    Box::into_raw(Box::new(PhpNitroRenderer {
        text_renderer: TextRenderer::new(),
    }))
}

/// # Safety
/// `renderer` must be a pointer previously returned by
/// `phpnitro_render_new` and not already freed.
#[no_mangle]
pub unsafe extern "C" fn phpnitro_render_free(renderer: *mut PhpNitroRenderer) {
    if !renderer.is_null() {
        drop(Box::from_raw(renderer));
    }
}

/// Rust-owned rasterized frame — RGBA8, premultiplied alpha (tiny-skia's
/// native `Pixmap` format, exposed as-is; every consumer already needs to
/// convert to whatever its own native bitmap format is, so no format
/// choice here is truly zero-copy on every platform anyway).
pub struct PhpNitroFrame {
    pixmap: Pixmap,
}

/// Rasterizes one `Canvas::toJson()` envelope into a new frame. Returns
/// null on failure (malformed JSON, zero width/height, or an internal
/// panic) — check `phpnitro_render_last_error()` for why.
///
/// # Safety
/// `renderer` must be a live pointer from `phpnitro_render_new`.
/// `envelope_json` must be null or point to a NUL-terminated UTF-8 string
/// valid for the duration of this call; it is read, never retained.
#[no_mangle]
pub unsafe extern "C" fn phpnitro_render_frame(
    renderer: *mut PhpNitroRenderer,
    envelope_json: *const c_char,
    width_px: u32,
    height_px: u32,
    elapsed_ms: u64,
) -> *mut PhpNitroFrame {
    let outcome = std::panic::catch_unwind(std::panic::AssertUnwindSafe(|| {
        let Some(renderer) = renderer.as_mut() else {
            set_last_error("phpnitro_render_frame: renderer is null");
            return None;
        };
        let Some(json) = borrow_str(envelope_json) else {
            set_last_error("phpnitro_render_frame: envelope_json is null or not valid UTF-8");
            return None;
        };
        let envelope = match protocol::decode_envelope(json) {
            Ok(envelope) => envelope,
            Err(error) => {
                set_last_error(format!("phpnitro_render_frame: {error}"));
                return None;
            }
        };
        let Some(mut pixmap) = Pixmap::new(width_px, height_px) else {
            set_last_error("phpnitro_render_frame: width_px and height_px must both be > 0");
            return None;
        };

        raster::render_commands(&mut pixmap, &envelope.commands, elapsed_ms, &mut renderer.text_renderer);

        Some(Box::into_raw(Box::new(PhpNitroFrame { pixmap })))
    }));

    match outcome {
        Ok(Some(frame)) => frame,
        Ok(None) => std::ptr::null_mut(),
        Err(_) => {
            set_last_error("phpnitro_render_frame: internal panic while rendering");
            std::ptr::null_mut()
        }
    }
}

/// # Safety
/// `frame` must be a live pointer from `phpnitro_render_frame`. The
/// returned pointer is borrowed — valid until `phpnitro_render_free_frame`
/// is called on the same `frame`, never to be freed separately.
#[no_mangle]
pub unsafe extern "C" fn phpnitro_render_frame_pixels(frame: *const PhpNitroFrame) -> *const u8 {
    frame.as_ref().map_or(std::ptr::null(), |frame| frame.pixmap.data().as_ptr())
}

/// # Safety
/// `frame` must be a live pointer from `phpnitro_render_frame`.
#[no_mangle]
pub unsafe extern "C" fn phpnitro_render_frame_stride(frame: *const PhpNitroFrame) -> u32 {
    frame.as_ref().map_or(0, |frame| frame.pixmap.width() * 4)
}

/// # Safety
/// `frame` must be a live pointer from `phpnitro_render_frame`.
#[no_mangle]
pub unsafe extern "C" fn phpnitro_render_frame_width(frame: *const PhpNitroFrame) -> u32 {
    frame.as_ref().map_or(0, |frame| frame.pixmap.width())
}

/// # Safety
/// `frame` must be a live pointer from `phpnitro_render_frame`.
#[no_mangle]
pub unsafe extern "C" fn phpnitro_render_frame_height(frame: *const PhpNitroFrame) -> u32 {
    frame.as_ref().map_or(0, |frame| frame.pixmap.height())
}

/// # Safety
/// `frame` must be a pointer previously returned by
/// `phpnitro_render_frame` and not already freed.
#[no_mangle]
pub unsafe extern "C" fn phpnitro_render_free_frame(frame: *mut PhpNitroFrame) {
    if !frame.is_null() {
        drop(Box::from_raw(frame));
    }
}

/// Rust-owned hit-test result — the action string and optional meta JSON
/// are owned here so a caller only ever needs one free call, not one per
/// getter.
pub struct PhpNitroHit {
    action: CString,
    meta_json: CString,
    rect: (f32, f32, f32, f32),
}

/// Finds the first action a tap at `(tap_x, tap_y)` lands on. Returns null
/// both on a genuine "nothing hit" (not an error) AND on a decode
/// failure — call `phpnitro_render_last_error()` to tell them apart if
/// that distinction matters to the caller.
///
/// # Safety
/// `envelope_json` and `interaction_state_json` must each be null or
/// point to a NUL-terminated UTF-8 string valid for the duration of this
/// call.
#[no_mangle]
pub unsafe extern "C" fn phpnitro_render_hit_test(
    envelope_json: *const c_char,
    tap_x: f32,
    tap_y: f32,
    interaction_state_json: *const c_char,
) -> *mut PhpNitroHit {
    let outcome = std::panic::catch_unwind(std::panic::AssertUnwindSafe(|| {
        let Some(json) = borrow_str(envelope_json) else {
            set_last_error("phpnitro_render_hit_test: envelope_json is null or not valid UTF-8");
            return None;
        };
        let envelope = match protocol::decode_envelope(json) {
            Ok(envelope) => envelope,
            Err(error) => {
                set_last_error(format!("phpnitro_render_hit_test: {error}"));
                return None;
            }
        };
        let state = interaction_state_from_json(borrow_str(interaction_state_json));

        hittest::hit_test(&envelope, tap_x, tap_y, &state).map(|hit| {
            let action = CString::new(hit.action).unwrap_or_default();
            let meta_json = hit.meta.map_or_else(|| "null".to_string(), |meta| meta.to_string());
            let meta_json = CString::new(meta_json).unwrap_or_default();
            Box::into_raw(Box::new(PhpNitroHit {
                action,
                meta_json,
                rect: hit.rect,
            }))
        })
    }));

    match outcome {
        Ok(Some(hit)) => hit,
        Ok(None) => std::ptr::null_mut(),
        Err(_) => {
            set_last_error("phpnitro_render_hit_test: internal panic while hit-testing");
            std::ptr::null_mut()
        }
    }
}

/// # Safety
/// `hit` must be a live pointer from `phpnitro_render_hit_test`. The
/// returned pointer is borrowed — valid until `phpnitro_render_free_hit`
/// is called on the same `hit`.
#[no_mangle]
pub unsafe extern "C" fn phpnitro_render_hit_action(hit: *const PhpNitroHit) -> *const c_char {
    hit.as_ref().map_or(std::ptr::null(), |hit| hit.action.as_ptr())
}

/// # Safety
/// `hit` must be a live pointer from `phpnitro_render_hit_test`. Same
/// borrowed-pointer contract as `phpnitro_render_hit_action`. Returns the
/// literal string `"null"` (valid JSON) when the region carried no meta.
#[no_mangle]
pub unsafe extern "C" fn phpnitro_render_hit_meta_json(hit: *const PhpNitroHit) -> *const c_char {
    hit.as_ref().map_or(std::ptr::null(), |hit| hit.meta_json.as_ptr())
}

/// Writes `(left, top, right, bottom)` into the 4 out-parameters. Any of
/// them may be null to skip that field. No-op if `hit` is null.
///
/// # Safety
/// `hit` must be a live pointer from `phpnitro_render_hit_test`. Each
/// non-null out-parameter must point to valid, writable `f32` storage.
#[no_mangle]
pub unsafe extern "C" fn phpnitro_render_hit_rect(
    hit: *const PhpNitroHit,
    left: *mut f32,
    top: *mut f32,
    right: *mut f32,
    bottom: *mut f32,
) {
    let Some(hit) = hit.as_ref() else { return };
    if !left.is_null() {
        *left = hit.rect.0;
    }
    if !top.is_null() {
        *top = hit.rect.1;
    }
    if !right.is_null() {
        *right = hit.rect.2;
    }
    if !bottom.is_null() {
        *bottom = hit.rect.3;
    }
}

/// # Safety
/// `hit` must be a pointer previously returned by
/// `phpnitro_render_hit_test` and not already freed.
#[no_mangle]
pub unsafe extern "C" fn phpnitro_render_free_hit(hit: *mut PhpNitroHit) {
    if !hit.is_null() {
        drop(Box::from_raw(hit));
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use std::ffi::CString as StdCString;

    #[test]
    fn version_round_trips_through_the_ffi_boundary() {
        let ptr = phpnitro_render_version();
        let version = unsafe { CStr::from_ptr(ptr) }.to_str().unwrap();
        assert_eq!(version, env!("CARGO_PKG_VERSION"));
    }

    #[test]
    fn render_frame_round_trips_a_real_fixture() {
        unsafe {
            let renderer = phpnitro_render_new();
            let json = StdCString::new(
                r##"{"commands":[{"type":"rect","x":0,"y":0,"width":10,"height":10,"color":"#FF0000","radius":0}],"hitRegions":[],"contentHeight":10}"##,
            )
            .unwrap();
            let frame = phpnitro_render_frame(renderer, json.as_ptr(), 20, 20, 0);
            assert!(!frame.is_null());
            assert_eq!(phpnitro_render_frame_width(frame), 20);
            assert_eq!(phpnitro_render_frame_height(frame), 20);
            assert_eq!(phpnitro_render_frame_stride(frame), 80);
            let pixels = phpnitro_render_frame_pixels(frame);
            assert!(!pixels.is_null());
            phpnitro_render_free_frame(frame);
            phpnitro_render_free(renderer);
        }
    }

    #[test]
    fn render_frame_returns_null_and_sets_last_error_on_malformed_json() {
        unsafe {
            let renderer = phpnitro_render_new();
            let bad_json = StdCString::new("{not valid json").unwrap();
            let frame = phpnitro_render_frame(renderer, bad_json.as_ptr(), 10, 10, 0);
            assert!(frame.is_null());
            let error = CStr::from_ptr(phpnitro_render_last_error()).to_str().unwrap();
            assert!(!error.is_empty());
            phpnitro_render_free(renderer);
        }
    }

    #[test]
    fn render_frame_returns_null_on_zero_dimensions() {
        unsafe {
            let renderer = phpnitro_render_new();
            let json = StdCString::new(r##"{"commands":[],"hitRegions":[],"contentHeight":0}"##).unwrap();
            let frame = phpnitro_render_frame(renderer, json.as_ptr(), 0, 0, 0);
            assert!(frame.is_null());
            phpnitro_render_free(renderer);
        }
    }

    #[test]
    fn hit_test_round_trips_a_real_hit() {
        unsafe {
            let json = StdCString::new(
                r##"{"commands":[],"hitRegions":[{"x":0,"y":0,"width":100,"height":40,"action":"submit:demo"}],"contentHeight":0}"##,
            )
            .unwrap();
            let hit = phpnitro_render_hit_test(json.as_ptr(), 50.0, 20.0, std::ptr::null());
            assert!(!hit.is_null());
            let action = CStr::from_ptr(phpnitro_render_hit_action(hit)).to_str().unwrap();
            assert_eq!(action, "submit:demo");
            let meta = CStr::from_ptr(phpnitro_render_hit_meta_json(hit)).to_str().unwrap();
            assert_eq!(meta, "null");
            let (mut left, mut top, mut right, mut bottom) = (0.0f32, 0.0f32, 0.0f32, 0.0f32);
            phpnitro_render_hit_rect(hit, &mut left, &mut top, &mut right, &mut bottom);
            assert_eq!((left, top, right, bottom), (0.0, 0.0, 100.0, 40.0));
            phpnitro_render_free_hit(hit);
        }
    }

    #[test]
    fn hit_test_with_interaction_state_respects_scroll_offset() {
        unsafe {
            let json = StdCString::new(
                r##"{"commands":[],"hitRegions":[{"x":0,"y":100,"width":100,"height":40,"action":"item"}],"contentHeight":0}"##,
            )
            .unwrap();
            let state = StdCString::new(r##"{"scrollY":100.0}"##).unwrap();
            let hit = phpnitro_render_hit_test(json.as_ptr(), 50.0, 10.0, state.as_ptr());
            assert!(!hit.is_null());
            phpnitro_render_free_hit(hit);
        }
    }

    #[test]
    fn hit_test_on_empty_space_returns_null_without_an_error() {
        unsafe {
            let json = StdCString::new(r##"{"commands":[],"hitRegions":[],"contentHeight":0}"##).unwrap();
            let hit = phpnitro_render_hit_test(json.as_ptr(), 999.0, 999.0, std::ptr::null());
            assert!(hit.is_null());
        }
    }
}
