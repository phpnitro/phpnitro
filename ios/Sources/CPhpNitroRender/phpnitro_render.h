/*
 * phpnitro_render.h — C ABI for the shared PhpNitro rendering core
 * (rust/phpnitro-render). Hand-written (no cbindgen) to avoid a dev-tool
 * install this early — the surface is small enough to keep in sync by
 * hand, and every function signature here has a matching `extern "C"` in
 * src/lib.rs (kept in the same order for easy comparison).
 *
 * Ownership: Rust allocates and frees every handle it hands out. Never
 * call free()/delete on a returned pointer directly — only ever pass it
 * to the matching phpnitro_render_free_* function. Every getter that
 * returns a `const char *` returns a BORROWED pointer, valid only until
 * the owning handle (frame/hit) is freed — never free those separately.
 *
 * Threading: phpnitro_render_last_error() is thread-local. A
 * PhpNitroRenderer is not documented as thread-safe for concurrent calls
 * — serialize access to one instance from a single thread, or create one
 * instance per thread if you need concurrency.
 */

#ifndef PHPNITRO_RENDER_H
#define PHPNITRO_RENDER_H

#include <stdint.h>

#ifdef __cplusplus
extern "C" {
#endif

/* Opaque handle to the loaded fonts/shaping state. Font loading isn't
 * free — create ONE of these per app lifetime (or per screen at most),
 * never one per frame. */
typedef struct PhpNitroRenderer PhpNitroRenderer;

/* Opaque handle to one rasterized frame (RGBA8, premultiplied alpha —
 * tiny-skia's native Pixmap format, exposed as-is). */
typedef struct PhpNitroFrame PhpNitroFrame;

/* Opaque handle to one hit-test result. */
typedef struct PhpNitroHit PhpNitroHit;

/* ---- Version / diagnostics --------------------------------------- */

/* Returns this build's crate version (e.g. "0.1.0"), NUL-terminated,
 * valid for the lifetime of the process. Never returns NULL. */
const char *phpnitro_render_version(void);

/* Last error set by this thread's most recent call into this library, or
 * NULL if that call didn't fail. Borrowed pointer, valid until the next
 * call into this library on the same thread — never free it. */
const char *phpnitro_render_last_error(void);

/* ---- Renderer lifecycle ------------------------------------------- */

/* Creates a new renderer (loads the 3 bundled fonts). Never returns
 * NULL. Free with phpnitro_render_free(). */
PhpNitroRenderer *phpnitro_render_new(void);

/* Frees a renderer created by phpnitro_render_new(). Safe to call with
 * NULL (no-op). */
void phpnitro_render_free(PhpNitroRenderer *renderer);

/* ---- Rendering ------------------------------------------------------
 *
 * envelope_json is the FULL JSON object Engine\Native\Canvas::toJson()
 * produces on the PHP side (not just its "commands" array) — decoded
 * once per call, read-only, not retained past the call.
 */

/* Rasterizes one envelope into a new (width_px x height_px) frame.
 *
 * previous_envelope_json is optional (NULL = no transition, the common
 * case — first render, or a same-screen refetch that never wants one).
 * When non-NULL, it's blended with envelope_json into a crossfade/hero
 * transition (see rust/phpnitro-render/src/transition.rs) driven by
 * transition_elapsed_ms — wall-clock milliseconds since envelope_json
 * became the active screen (a single shared clock for both the whole-
 * screen crossfade and any flying hero subtrees, exactly mirroring
 * Android's own fadeProgress/heroProgress). A malformed
 * previous_envelope_json is treated the same as NULL, not a hard error.
 *
 * elapsed_ms drives spinner/skeleton animation (wall-clock milliseconds
 * since any fixed, monotonic epoch the caller chooses — e.g. process
 * start). Pass 0 for a caller that never animates.
 *
 * Returns NULL on failure (malformed envelope_json, or width_px/height_px
 * == 0) — call phpnitro_render_last_error() to find out why. Free a
 * non-NULL result with phpnitro_render_free_frame(). */
PhpNitroFrame *phpnitro_render_frame(
    PhpNitroRenderer *renderer,
    const char *envelope_json,
    const char *previous_envelope_json,
    uint64_t transition_elapsed_ms,
    uint32_t width_px,
    uint32_t height_px,
    uint64_t elapsed_ms
);

/* Raw RGBA8 pixel buffer for `frame` — row-major, premultiplied alpha,
 * `phpnitro_render_frame_stride(frame)` bytes per row. Borrowed pointer,
 * valid until phpnitro_render_free_frame(frame); never free it
 * separately, never write through it. */
const uint8_t *phpnitro_render_frame_pixels(const PhpNitroFrame *frame);

/* Bytes per row of the pixel buffer above (always width_px * 4 for this
 * format, but callers should read this rather than assume it). */
uint32_t phpnitro_render_frame_stride(const PhpNitroFrame *frame);
uint32_t phpnitro_render_frame_width(const PhpNitroFrame *frame);
uint32_t phpnitro_render_frame_height(const PhpNitroFrame *frame);

/* Frees a frame created by phpnitro_render_frame(). Safe to call with
 * NULL (no-op). Invalidates any pointer previously returned by
 * phpnitro_render_frame_pixels() for this frame. */
void phpnitro_render_free_frame(PhpNitroFrame *frame);

/* ---- Hit-testing -----------------------------------------------------
 *
 * interaction_state_json is an optional JSON object describing
 * caller-owned interaction state this crate has no memory of itself:
 *   {
 *     "scrollY": 0.0,                    // the whole screen's vertical scroll
 *     "activePanel": { "tabsKey": 1 },   // clientPanel.key -> active index
 *     "axisOffset": { "railKey": 40.0 }  // hScroll/vScroll.key -> local drag offset
 *   }
 * Every field is optional; NULL or "{}" means "no interactivity yet".
 */

/* Finds the first action a tap at (tap_x, tap_y) lands on — same
 * coordinate space as width_px/height_px passed to
 * phpnitro_render_frame(). Returns NULL both when the tap hit nothing
 * (a normal outcome) and on a decode failure — check
 * phpnitro_render_last_error() to tell those apart if that distinction
 * matters. Free a non-NULL result with phpnitro_render_free_hit(). */
PhpNitroHit *phpnitro_render_hit_test(
    const char *envelope_json,
    float tap_x,
    float tap_y,
    const char *interaction_state_json
);

/* The action string this hit fired (e.g. "submit:demo"). Borrowed
 * pointer, valid until phpnitro_render_free_hit(hit). */
const char *phpnitro_render_hit_action(const PhpNitroHit *hit);

/* The hit region's `meta` object as a JSON string — the literal text
 * "null" (a valid JSON value) when the region carried no meta. Borrowed
 * pointer, same lifetime as phpnitro_render_hit_action() above. */
const char *phpnitro_render_hit_meta_json(const PhpNitroHit *hit);

/* Writes (left, top, right, bottom) into the 4 out-parameters, in the
 * same coordinate space as the tap. Any of the 4 pointers may be NULL to
 * skip that field. No-op if `hit` is NULL. */
void phpnitro_render_hit_rect(
    const PhpNitroHit *hit,
    float *left,
    float *top,
    float *right,
    float *bottom
);

/* Frees a hit result created by phpnitro_render_hit_test(). Safe to call
 * with NULL (no-op). */
void phpnitro_render_free_hit(PhpNitroHit *hit);

#ifdef __cplusplus
}
#endif

#endif /* PHPNITRO_RENDER_H */
