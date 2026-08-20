package com.mobile.engine

import androidx.test.ext.junit.runners.AndroidJUnit4
import com.phpnitro.engine.RustRenderer
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Test
import org.junit.runner.RunWith

/**
 * The first-ever real-JVM proof that RustRenderer/jni_bridge.rs actually
 * work on a device — see RustRenderer.kt's own "Honesty" doc comment
 * ("this has never been called by a real JVM"). Mirrors
 * RustRendererTests.cs's RenderFrameProducesTheExpectedPixelForAPlainRedRect
 * exactly, the same minimal fixture/assertion every other platform's own
 * Rust binding test already uses (Windows/macOS/Linux/iOS), just via JNI
 * instead of P/Invoke/C-interop/ctypes.
 *
 * Run with: `./gradlew :app:connectedDebugAndroidTest` against a real
 * connected device/emulator — same command NativeUiE2ETest.kt already
 * uses, this is the exact same class alongside it, not a separate module.
 */
@RunWith(AndroidJUnit4::class)
class RustRendererDeviceTest {
    @Test
    fun renderFrameProducesTheExpectedPixelForAPlainRedRect() {
        val renderer = RustRenderer()
        try {
            val envelope = """
                {"commands":[{"type":"rect","x":0,"y":0,"width":10,"height":10,"color":"#FF0000","radius":0}],
                 "hitRegions":[],"contentHeight":10}
            """.trimIndent()

            val frame = renderer.renderFrame(envelope, 20, 20)
            assertNotNull("renderFrame() returned null — the Rust library failed to render", frame)
            assertEquals(20, frame!!.width)
            assertEquals(20, frame.height)
            assertEquals(80, frame.stride)

            // (5, 5) sits inside the 10x10 red rect — RGBA8 premultiplied,
            // opaque red: [255, 0, 0, 255].
            val offset = 5 * frame.stride + 5 * 4
            assertEquals(255, frame.pixels[offset].toInt() and 0xFF)
            assertEquals(0, frame.pixels[offset + 1].toInt() and 0xFF)
            assertEquals(0, frame.pixels[offset + 2].toInt() and 0xFF)
            assertEquals(255, frame.pixels[offset + 3].toInt() and 0xFF)
        } finally {
            renderer.close()
        }
    }

    @Test
    fun hitTestFindsTheActionAtTheTappedPoint() {
        val envelope = """
            {"commands":[],"hitRegions":[{"x":0,"y":0,"width":20,"height":20,"action":"navigate:settings"}],
             "contentHeight":20}
        """.trimIndent()

        val hit = RustRenderer.hitTest(envelope, 5f, 5f)
        assertNotNull("hitTest() returned null — expected a hit inside the region", hit)
        assertEquals("navigate:settings", hit!!.action)
    }
}
