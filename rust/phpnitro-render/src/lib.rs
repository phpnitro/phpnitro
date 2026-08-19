//! FFI entry points for the shared PhpNitro rendering core.
//!
//! Everything reachable from other languages (Kotlin/JNI, Swift, Python
//! ctypes, C# P/Invoke) lives here as thin `extern "C"` wrappers; the real
//! logic lives in the sibling modules and is added incrementally.

use std::ffi::CString;
use std::os::raw::c_char;

/// Returns the crate's own version as a NUL-terminated C string, valid for
/// the lifetime of the program (leaked once, not per-call).
#[no_mangle]
pub extern "C" fn phpnitro_render_version() -> *const c_char {
    static VERSION: std::sync::OnceLock<CString> = std::sync::OnceLock::new();
    VERSION
        .get_or_init(|| CString::new(env!("CARGO_PKG_VERSION")).expect("version has no NUL bytes"))
        .as_ptr()
}

#[cfg(test)]
mod tests {
    use super::*;
    use std::ffi::CStr;

    #[test]
    fn version_round_trips_through_the_ffi_boundary() {
        let ptr = phpnitro_render_version();
        let version = unsafe { CStr::from_ptr(ptr) }.to_str().unwrap();
        assert_eq!(version, env!("CARGO_PKG_VERSION"));
    }
}
