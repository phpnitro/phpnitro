/* Real C consumer of phpnitro_render.h, compiled and linked against the
 * actual compiled library at test time (see ffi_smoke.rs) — proves the
 * header and the library genuinely agree with each other, not just that
 * the Rust side compiles in isolation. Exits non-zero (via assert) on
 * any mismatch. */
#include "phpnitro_render.h"
#include <assert.h>
#include <stdio.h>
#include <string.h>

int main(void) {
    const char *version = phpnitro_render_version();
    assert(version != NULL);
    printf("phpnitro-render version: %s\n", version);

    PhpNitroRenderer *renderer = phpnitro_render_new();
    assert(renderer != NULL);

    const char *envelope =
        "{\"commands\":["
        "{\"type\":\"rect\",\"x\":0,\"y\":0,\"width\":10,\"height\":10,\"color\":\"#FF0000\",\"radius\":0}"
        "],\"hitRegions\":[{\"x\":0,\"y\":0,\"width\":10,\"height\":10,\"action\":\"tap:me\"}],\"contentHeight\":10}";

    PhpNitroFrame *frame = phpnitro_render_frame(renderer, envelope, NULL, 0, 20, 20, 0, NULL);
    assert(frame != NULL);
    assert(phpnitro_render_frame_width(frame) == 20);
    assert(phpnitro_render_frame_height(frame) == 20);
    assert(phpnitro_render_frame_stride(frame) == 80);
    const unsigned char *pixels = phpnitro_render_frame_pixels(frame);
    assert(pixels != NULL);
    phpnitro_render_free_frame(frame);

    /* Malformed JSON must fail cleanly, not crash. */
    PhpNitroFrame *bad = phpnitro_render_frame(renderer, "{not json", NULL, 0, 10, 10, 0, NULL);
    assert(bad == NULL);
    const char *error = phpnitro_render_last_error();
    assert(error != NULL);
    assert(strlen(error) > 0);

    PhpNitroHit *hit = phpnitro_render_hit_test(envelope, 5.0f, 5.0f, NULL);
    assert(hit != NULL);
    const char *action = phpnitro_render_hit_action(hit);
    assert(action != NULL);
    assert(strcmp(action, "tap:me") == 0);
    float left, top, right, bottom;
    phpnitro_render_hit_rect(hit, &left, &top, &right, &bottom);
    assert(left == 0.0f && top == 0.0f && right == 10.0f && bottom == 10.0f);
    phpnitro_render_free_hit(hit);

    PhpNitroHit *miss = phpnitro_render_hit_test(envelope, 999.0f, 999.0f, NULL);
    assert(miss == NULL);

    phpnitro_render_free(renderer);

    printf("ffi_smoke: OK\n");
    return 0;
}
