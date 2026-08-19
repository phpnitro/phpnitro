//! Compiles `ffi_smoke.c` — a real C program — against the actual
//! compiled `libphpnitro_render` and `include/phpnitro_render.h`, links
//! it, runs it, and checks it exits 0. This is the one test in this
//! crate that proves the hand-written C header and the Rust
//! implementation genuinely agree, not just that each compiles on its
//! own. Requires a C compiler (`cc`) on PATH — skipped with a clear
//! message if none is found, rather than failing CI on a missing
//! toolchain unrelated to this crate's own code.

use std::path::PathBuf;
use std::process::Command;

fn find_cc() -> Option<&'static str> {
    ["cc", "gcc", "clang"].into_iter().find(|candidate| {
        Command::new(candidate)
            .arg("--version")
            .output()
            .map(|output| output.status.success())
            .unwrap_or(false)
    })
}

/// The directory holding this test's own compiled binary — its parent is
/// `target/<profile>/`, where `cargo build`'s `crate-type = ["cdylib", ...]`
/// already places `libphpnitro_render.so` as a side effect of building
/// this same crate (confirmed: no separate build step needed).
fn target_profile_dir() -> PathBuf {
    let exe = std::env::current_exe().expect("current_exe");
    exe.parent().expect("deps dir").parent().expect("profile dir").to_path_buf()
}

#[test]
fn c_program_compiles_links_and_runs_against_the_real_library() {
    let Some(cc) = find_cc() else {
        eprintln!("ffi_smoke: no C compiler found on PATH, skipping");
        return;
    };

    let manifest_dir = PathBuf::from(env!("CARGO_MANIFEST_DIR"));
    let include_dir = manifest_dir.join("include");
    let c_source = manifest_dir.join("tests/ffi_smoke.c");
    let lib_dir = target_profile_dir();

    let out_binary = std::env::temp_dir().join(format!("phpnitro_ffi_smoke_{}", std::process::id()));

    let compile = Command::new(cc)
        .arg(&c_source)
        .arg("-I")
        .arg(&include_dir)
        .arg("-L")
        .arg(&lib_dir)
        .arg("-lphpnitro_render")
        .arg("-o")
        .arg(&out_binary)
        .output()
        .expect("failed to invoke the C compiler");
    assert!(
        compile.status.success(),
        "compiling ffi_smoke.c failed:\nstdout: {}\nstderr: {}",
        String::from_utf8_lossy(&compile.stdout),
        String::from_utf8_lossy(&compile.stderr),
    );

    let run = Command::new(&out_binary)
        .env("LD_LIBRARY_PATH", &lib_dir)
        .output()
        .unwrap_or_else(|e| panic!("failed to run {out_binary:?}: {e}"));

    let _ = std::fs::remove_file(&out_binary);

    assert!(
        run.status.success(),
        "ffi_smoke.c exited non-zero:\nstdout: {}\nstderr: {}",
        String::from_utf8_lossy(&run.stdout),
        String::from_utf8_lossy(&run.stderr),
    );
    assert!(String::from_utf8_lossy(&run.stdout).contains("ffi_smoke: OK"));
}
