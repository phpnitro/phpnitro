package com.phpnitro.engine

import org.json.JSONObject

/**
 * Kotlin/JNI counterpart of linux/phpnitro_desktop/rust_render.py (ctypes),
 * windows/PhpNitroDesktop.Render/RustRenderer.cs (P/Invoke), and
 * macos/Sources/RustMacRenderer/RustMacRenderer.swift +
 * ios/Sources/RustNativeRenderer/RustNativeRenderer.swift (C-interop) —
 * calls into rust/phpnitro-render's src/jni_bridge.rs, not the plain C ABI
 * (include/phpnitro_render.h) those four other ports use directly. JNI
 * has its own calling convention, so this is a genuinely separate binding
 * layer, not a Kotlin translation of the C header.
 *
 * Every `nativeXxx` declaration below is a STATIC method (inside
 * `companion object`), deliberately — jni_bridge.rs's own Rust functions
 * all take a `JClass` as their second parameter (the usual shape for a
 * function that doesn't need a specific Kotlin object instance, only an
 * explicit `handle: Long` it's handed), not a `JObject`. Declaring any of
 * these as a Kotlin INSTANCE method instead would make the JVM pass a
 * `jobject` (`this`) at that same call slot instead — a real, silent JNI
 * signature mismatch this project has no way to catch except by getting
 * this consistent up front. `RustRenderer` the class still behaves like a
 * normal stateful object (it owns a `handle`); its instance methods just
 * delegate to these static natives, passing that handle explicitly.
 *
 * # Honesty
 *
 * `android-e2e-test` (the only CI job that would run code on a real
 * emulator) is still disabled (see `.github/workflows/ci.yml`'s own
 * comment on that job), so there is still no CI-executed proof this
 * works — but `renderFrame()`/`hitTest()` HAVE now been confirmed working
 * on a real device by hand (Infinix X6532, Android 14,
 * `RustRendererDeviceTest.kt` run via `./gradlew
 * :app:connectedDebugAndroidTest`), the first time any of this ever ran
 * on a real JVM. Two things had to be true first, neither of them
 * automatic yet: `libphpnitro_render.so` has to actually exist under
 * `android/engine/src/main/jniLibs/<abi>/` (never committed — see
 * `bin/build-rust-android.sh`, which cross-compiles it locally the same
 * way CI does), and a real device/emulator has to be connected via `adb`.
 * Still NOT wired into `NativeCanvasView.kt`'s real rendering path — one
 * passing smoke test proves the JNI bridge itself works, not that it's
 * ready to replace the existing Kotlin/Canvas renderer for real screens.
 */
class RustRenderUnavailableException(message: String) : Exception(message)

data class RenderedFrame(val width: Int, val height: Int, val stride: Int, val pixels: ByteArray)

data class HitResult(
    val action: String,
    val metaJson: String,
    val left: Float,
    val top: Float,
    val right: Float,
    val bottom: Float,
)

data class SliderHitResult(val key: String, val action: String, val value: Float)

class RustRenderer {
    private var handle: Long = 0

    init {
        handle = nativeNew()
        if (handle == 0L) {
            throw RustRenderUnavailableException("nativeNew() returned 0")
        }
    }

    /**
     * Returns null on failure (malformed JSON, zero width/height).
     * `previousEnvelopeJson`/`transitionElapsedMs` drive a crossfade/hero
     * transition between it and `envelopeJson` (see
     * rust/phpnitro-render/src/transition.rs) — omit both (the defaults)
     * for a plain, untransitioned render, the same zero-extra-cost path
     * every existing caller already takes. `interactionStateJson` is the
     * same shape `hitTest()` already takes (activePanel/axisOffset/
     * sliderValue) — omit it (the default) to paint every clientPanel/
     * hScroll/vScroll/slider at its server-authored resting state.
     */
    fun renderFrame(
        envelopeJson: String,
        widthPx: Int,
        heightPx: Int,
        elapsedMs: Long = 0,
        previousEnvelopeJson: String? = null,
        transitionElapsedMs: Long = 0,
        interactionStateJson: String? = null,
    ): RenderedFrame? {
        val packed = nativeRenderFrame(handle, envelopeJson, previousEnvelopeJson, transitionElapsedMs, widthPx, heightPx, elapsedMs, interactionStateJson) ?: return null
        // [width:i32 LE][height:i32 LE][stride:i32 LE][premultiplied RGBA8 pixels...]
        // — see jni_bridge.rs's own nativeRenderFrame doc comment for why
        // this is one packed ByteArray instead of separate accessors.
        val width = readLeInt32(packed, 0)
        val height = readLeInt32(packed, 4)
        val stride = readLeInt32(packed, 8)
        val pixels = packed.copyOfRange(12, packed.size)
        return RenderedFrame(width, height, stride, pixels)
    }

    fun close() {
        if (handle != 0L) {
            nativeFree(handle)
            handle = 0
        }
    }

    @Suppress("removal", "deprecation")
    protected fun finalize() {
        close()
    }

    companion object {
        init {
            System.loadLibrary("phpnitro_render")
        }

        val version: String get() = nativeVersion()

        /**
         * Finds the first action a tap at (tapX, tapY) lands on — same
         * coordinate space as the width/height passed to renderFrame().
         * Returns null both when the tap hit nothing (a normal outcome)
         * and on a decode failure — this binding doesn't distinguish the
         * two, unlike the C ABI's separate last-error mechanism, since
         * doing so isn't worth a second JNI round trip for a case where
         * "nothing happened" is the overwhelmingly common answer either
         * way.
         */
        fun hitTest(envelopeJson: String, tapX: Float, tapY: Float, interactionStateJson: String? = null): HitResult? {
            val resultJson = nativeHitTest(envelopeJson, tapX, tapY, interactionStateJson ?: "") ?: return null
            val obj = JSONObject(resultJson)
            val rect = obj.getJSONArray("rect")
            return HitResult(
                action = obj.getString("action"),
                metaJson = obj.getString("metaJson"),
                left = rect.getDouble(0).toFloat(),
                top = rect.getDouble(1).toFloat(),
                right = rect.getDouble(2).toFloat(),
                bottom = rect.getDouble(3).toFloat(),
            )
        }

        /**
         * A slider tap isn't expressible through hitTest()'s own
         * hitRegions[] mechanism — the value depends on where within the
         * slider you tapped, not a single precomputed action (see
         * rust/phpnitro-render/src/hittest.rs's own doc comment).
         */
        fun sliderHitTest(envelopeJson: String, tapX: Float, tapY: Float, interactionStateJson: String? = null): SliderHitResult? {
            val resultJson = nativeSliderHitTest(envelopeJson, tapX, tapY, interactionStateJson ?: "") ?: return null
            val obj = JSONObject(resultJson)
            return SliderHitResult(
                key = obj.getString("key"),
                action = obj.getString("action"),
                value = obj.getDouble("value").toFloat(),
            )
        }

        private fun readLeInt32(bytes: ByteArray, offset: Int): Int =
            (bytes[offset].toInt() and 0xFF) or
                ((bytes[offset + 1].toInt() and 0xFF) shl 8) or
                ((bytes[offset + 2].toInt() and 0xFF) shl 16) or
                ((bytes[offset + 3].toInt() and 0xFF) shl 24)

        @JvmStatic private external fun nativeVersion(): String
        @JvmStatic private external fun nativeNew(): Long
        @JvmStatic private external fun nativeFree(handle: Long)
        @JvmStatic private external fun nativeRenderFrame(
            handle: Long,
            envelopeJson: String,
            previousEnvelopeJson: String?,
            transitionElapsedMs: Long,
            widthPx: Int,
            heightPx: Int,
            elapsedMs: Long,
            interactionStateJson: String?,
        ): ByteArray?
        @JvmStatic private external fun nativeHitTest(
            envelopeJson: String,
            tapX: Float,
            tapY: Float,
            interactionStateJson: String,
        ): String?
        @JvmStatic private external fun nativeSliderHitTest(
            envelopeJson: String,
            tapX: Float,
            tapY: Float,
            interactionStateJson: String,
        ): String?
    }
}
