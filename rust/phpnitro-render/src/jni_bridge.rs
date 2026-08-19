//! JNI bridge for Android — the Kotlin counterpart of `lib.rs`'s plain C
//! ABI. A separate binding layer rather than reusing `phpnitro_render.h`
//! directly: JNI has its own calling convention (`extern "system"`,
//! `JNIEnv`/`JClass` parameters, `jstring`/`jbyteArray` instead of raw
//! `char*`/`uint8_t*`, mangled `Java_<package>_<Class>_<method>` symbol
//! names) that Kotlin's `external fun` declarations require — there is no
//! way to `System.loadLibrary()` + call the plain C functions directly
//! from Kotlin the way ctypes/P-Invoke/Swift's C-interop do.
//!
//! Compiled only `#[cfg(target_os = "android")]` — this module (and its
//! one Android-only dependency, the `jni` crate) is invisible to every
//! other platform's build.
//!
//! # Honesty
//!
//! Every other platform integration in this crate's history (Linux,
//! Windows via the Linux .so, macOS natively, iOS cross-compiled to the
//! Simulator) got a REAL CI-executed test run — not just a compile check.
//! Android cannot: `android-e2e-test` (the only CI job that ever runs
//! code on a real emulator) is disabled (billing limit, see
//! `.github/workflows/ci.yml`'s own comment on that job), so nothing here
//! has ever actually been called by a JVM. `cargo build --target
//! aarch64-linux-android` / `gradle :app:assembleDebug` only prove this
//! compiles and links — not that a single JNI call across this boundary
//! actually works at runtime. Treat this module as unverified beyond
//! "the types line up," not as proven correct.

#![cfg(target_os = "android")]

use crate::hittest::{hit_test, InteractionState};
use crate::protocol::decode_envelope;
use crate::raster::render_commands;
use crate::text::TextRenderer;
use jni::objects::{JClass, JString};
use jni::sys::{jbyteArray, jfloat, jint, jlong, jstring};
use jni::JNIEnv;
use tiny_skia::Pixmap;

struct JniRenderer {
    text_renderer: TextRenderer,
}

fn jstring_to_string(env: &mut JNIEnv, value: &JString) -> String {
    env.get_string(value).map(|s| s.into()).unwrap_or_default()
}

fn new_jstring_or_null(env: &mut JNIEnv, value: &str) -> jstring {
    env.new_string(value).map(|s| s.into_raw()).unwrap_or(std::ptr::null_mut())
}

#[no_mangle]
pub extern "system" fn Java_com_phpnitro_engine_RustRenderer_nativeVersion<'local>(
    mut env: JNIEnv<'local>,
    _class: JClass<'local>,
) -> jstring {
    new_jstring_or_null(&mut env, env!("CARGO_PKG_VERSION"))
}

#[no_mangle]
pub extern "system" fn Java_com_phpnitro_engine_RustRenderer_nativeNew<'local>(
    _env: JNIEnv<'local>,
    _class: JClass<'local>,
) -> jlong {
    let renderer = Box::new(JniRenderer {
        text_renderer: TextRenderer::new(),
    });
    Box::into_raw(renderer) as jlong
}

/// # Safety (documented, not enforced by the type system — same as every
/// other platform's `free` function)
/// `handle` must be a value previously returned by `nativeNew` and not
/// already freed.
#[no_mangle]
pub extern "system" fn Java_com_phpnitro_engine_RustRenderer_nativeFree<'local>(
    _env: JNIEnv<'local>,
    _class: JClass<'local>,
    handle: jlong,
) {
    if handle != 0 {
        unsafe {
            drop(Box::from_raw(handle as *mut JniRenderer));
        }
    }
}

/// Returns a `ByteArray` shaped `[width:i32 LE][height:i32 LE][stride:i32
/// LE][premultiplied RGBA8 pixels...]`, or `null` on failure (malformed
/// JSON, zero width/height) — a single packed return value instead of
/// separate width/height/stride/pixel accessor calls (unlike the C ABI's
/// `PhpNitroFrame` handle), since JNI round-trips are comparatively
/// expensive and Kotlin has no raw-pointer type to hold an opaque frame
/// handle across several follow-up calls the way C/Swift/C# do.
#[no_mangle]
pub extern "system" fn Java_com_phpnitro_engine_RustRenderer_nativeRenderFrame<'local>(
    mut env: JNIEnv<'local>,
    _class: JClass<'local>,
    handle: jlong,
    envelope_json: JString<'local>,
    width_px: jint,
    height_px: jint,
    elapsed_ms: jlong,
) -> jbyteArray {
    if handle == 0 || width_px <= 0 || height_px <= 0 {
        return std::ptr::null_mut();
    }
    let renderer = unsafe { &mut *(handle as *mut JniRenderer) };

    let json = jstring_to_string(&mut env, &envelope_json);
    let Ok(envelope) = decode_envelope(&json) else {
        return std::ptr::null_mut();
    };
    let Some(mut pixmap) = Pixmap::new(width_px as u32, height_px as u32) else {
        return std::ptr::null_mut();
    };

    render_commands(&mut pixmap, &envelope.commands, elapsed_ms as u64, &mut renderer.text_renderer);

    let width = pixmap.width();
    let height = pixmap.height();
    let stride = width * 4;
    let mut buffer = Vec::with_capacity(12 + pixmap.data().len());
    buffer.extend_from_slice(&width.to_le_bytes());
    buffer.extend_from_slice(&height.to_le_bytes());
    buffer.extend_from_slice(&stride.to_le_bytes());
    buffer.extend_from_slice(pixmap.data());

    env.byte_array_from_slice(&buffer)
        .map(|array| array.into_raw())
        .unwrap_or(std::ptr::null_mut())
}

/// Returns a hit result as a JSON string (`{"action":...,"metaJson":...,
/// "rect":[left,top,right,bottom]}`), or `null` for a genuine "nothing
/// hit" — Kotlin re-parses this tiny JSON blob with `org.json.JSONObject`
/// (already used throughout `NativeCanvasView.kt`), rather than
/// constructing a typed Kotlin object from Rust via JNI's
/// `NewObject`/constructor-signature machinery, which would need this
/// crate to track a second, brittle contract (the exact constructor
/// descriptor string) with zero way to verify it here.
#[no_mangle]
pub extern "system" fn Java_com_phpnitro_engine_RustRenderer_nativeHitTest<'local>(
    mut env: JNIEnv<'local>,
    _class: JClass<'local>,
    envelope_json: JString<'local>,
    tap_x: jfloat,
    tap_y: jfloat,
    interaction_state_json: JString<'local>,
) -> jstring {
    let json = jstring_to_string(&mut env, &envelope_json);
    let Ok(envelope) = decode_envelope(&json) else {
        return std::ptr::null_mut();
    };

    let state_json = jstring_to_string(&mut env, &interaction_state_json);
    let state: InteractionState = if state_json.trim().is_empty() {
        InteractionState::default()
    } else {
        serde_json::from_str(&state_json).unwrap_or_default()
    };

    let Some(hit) = hit_test(&envelope, tap_x, tap_y, &state) else {
        return std::ptr::null_mut();
    };

    let result_json = serde_json::json!({
        "action": hit.action,
        "metaJson": hit.meta.map(|meta| meta.to_string()).unwrap_or_else(|| "null".to_string()),
        "rect": [hit.rect.0, hit.rect.1, hit.rect.2, hit.rect.3],
    })
    .to_string();
    new_jstring_or_null(&mut env, &result_json)
}
