package com.phpnitro.go

import android.Manifest
import android.content.pm.PackageManager
import android.graphics.Canvas
import android.graphics.Color
import android.graphics.Paint
import android.graphics.PorterDuff
import android.graphics.PorterDuffXfermode
import android.graphics.RectF
import android.os.Bundle
import android.view.Gravity
import android.view.View
import android.widget.FrameLayout
import android.widget.TextView
import android.widget.Toast
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.camera.core.CameraSelector
import androidx.camera.core.ImageAnalysis
import androidx.camera.core.ImageProxy
import androidx.camera.core.Preview
import androidx.camera.lifecycle.ProcessCameraProvider
import androidx.camera.view.PreviewView
import androidx.core.content.ContextCompat
import com.google.mlkit.vision.barcode.BarcodeScannerOptions
import com.google.mlkit.vision.barcode.BarcodeScanning
import com.google.mlkit.vision.barcode.common.Barcode
import com.google.mlkit.vision.common.InputImage
import java.util.concurrent.atomic.AtomicBoolean

/**
 * A live camera preview + ML Kit's on-device QR decoder (no network call,
 * no Google account) — points at whatever URL `phpx serve` encoded via
 * bin/QrCode.php, then hands off to the exact same renderIntent()
 * ConnectActivity's manual "Connecter" path uses. `handled` guards against
 * decoding and launching more than once — ML Kit keeps calling the
 * analyzer on every frame, and the camera preview stays visible (and
 * decoding) for the brief moment it takes NativeRenderPocActivity's own
 * Activity transition to actually take over the screen.
 */
class ScanActivity : AppCompatActivity() {
    private val handled = AtomicBoolean(false)
    private lateinit var previewView: PreviewView
    private lateinit var statusLabel: TextView

    private val requestCameraPermission = registerForActivityResult(ActivityResultContracts.RequestPermission()) { granted ->
        if (granted) {
            startCamera()
        } else {
            statusLabel.text = "Permission caméra refusée — reviens en arrière et utilise la saisie manuelle."
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        val density = resources.displayMetrics.density
        fun dp(value: Int): Int = (value * density).toInt()

        val root = FrameLayout(this)
        previewView = PreviewView(this)
        root.addView(previewView, FrameLayout.LayoutParams(FrameLayout.LayoutParams.MATCH_PARENT, FrameLayout.LayoutParams.MATCH_PARENT))
        root.addView(ViewfinderOverlay(this), FrameLayout.LayoutParams(FrameLayout.LayoutParams.MATCH_PARENT, FrameLayout.LayoutParams.MATCH_PARENT))

        // A plain clickable TextView styled as a round back chip — dark
        // translucent chrome over whatever's behind it, since that's a
        // live camera feed here, matching the QR/error card aesthetic
        // used elsewhere in this app.
        val backChip = TextView(this).apply {
            text = "←"
            textSize = 20f
            setTextColor(Color.WHITE)
            gravity = Gravity.CENTER
            background = android.graphics.drawable.GradientDrawable().apply {
                shape = android.graphics.drawable.GradientDrawable.OVAL
                setColor(Color.parseColor("#99000000"))
            }
            isClickable = true
            setOnClickListener { finish() }
        }
        root.addView(
            backChip,
            FrameLayout.LayoutParams(dp(44), dp(44)).apply {
                gravity = Gravity.TOP or Gravity.START
                topMargin = dp(24)
                leftMargin = dp(20)
            },
        )

        val titleLabel = TextView(this).apply {
            text = "Scanner un QR code"
            textSize = 17f
            setTypeface(typeface, android.graphics.Typeface.BOLD)
            setTextColor(Color.WHITE)
            gravity = Gravity.CENTER
        }
        root.addView(
            titleLabel,
            FrameLayout.LayoutParams(FrameLayout.LayoutParams.WRAP_CONTENT, FrameLayout.LayoutParams.WRAP_CONTENT).apply {
                gravity = Gravity.TOP or Gravity.CENTER_HORIZONTAL
                topMargin = dp(34)
            },
        )

        statusLabel = TextView(this).apply {
            text = "Vise le QR code affiché par `phpx serve`"
            textSize = 14f
            setTextColor(Color.parseColor("#F3F4F6"))
            gravity = Gravity.CENTER
            setPadding(dp(24), dp(16), dp(24), dp(16))
        }
        root.addView(
            statusLabel,
            FrameLayout.LayoutParams(FrameLayout.LayoutParams.MATCH_PARENT, FrameLayout.LayoutParams.WRAP_CONTENT).apply {
                gravity = Gravity.BOTTOM
                bottomMargin = dp(56)
            },
        )

        setContentView(root)

        if (ContextCompat.checkSelfPermission(this, Manifest.permission.CAMERA) == PackageManager.PERMISSION_GRANTED) {
            startCamera()
        } else {
            requestCameraPermission.launch(Manifest.permission.CAMERA)
        }
    }

    private fun startCamera() {
        val cameraProviderFuture = ProcessCameraProvider.getInstance(this)
        cameraProviderFuture.addListener({
            val cameraProvider = cameraProviderFuture.get()

            val preview = Preview.Builder().build().also {
                it.surfaceProvider = previewView.surfaceProvider
            }

            val scanner = BarcodeScanning.getClient(
                BarcodeScannerOptions.Builder().setBarcodeFormats(Barcode.FORMAT_QR_CODE).build(),
            )

            val analysis = ImageAnalysis.Builder()
                .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST)
                .build()
            analysis.setAnalyzer(ContextCompat.getMainExecutor(this)) { imageProxy ->
                processFrame(imageProxy, scanner)
            }

            try {
                cameraProvider.unbindAll()
                cameraProvider.bindToLifecycle(this, CameraSelector.DEFAULT_BACK_CAMERA, preview, analysis)
            } catch (e: Exception) {
                Toast.makeText(this, "Erreur caméra : ${e.message}", Toast.LENGTH_LONG).show()
                finish()
            }
        }, ContextCompat.getMainExecutor(this))
    }

    @androidx.camera.core.ExperimentalGetImage
    private fun processFrame(imageProxy: ImageProxy, scanner: com.google.mlkit.vision.barcode.BarcodeScanner) {
        val mediaImage = imageProxy.image
        if (mediaImage == null || handled.get()) {
            imageProxy.close()
            return
        }

        val image = InputImage.fromMediaImage(mediaImage, imageProxy.imageInfo.rotationDegrees)
        scanner.process(image)
            .addOnSuccessListener { barcodes ->
                val rawValue = barcodes.firstOrNull()?.rawValue
                val parsed = rawValue?.let { parseHostPort(it) }
                if (parsed != null && handled.compareAndSet(false, true)) {
                    val (host, port) = parsed
                    startActivity(renderIntent(this, host, port))
                    finish()
                }
            }
            .addOnCompleteListener {
                imageProxy.close()
            }
    }
}

/**
 * Dark scrim over the whole camera preview with a punched-out rounded
 * square + four corner brackets — the standard "aim here" affordance every
 * QR scanner (including the OS camera app's own) uses, so this doesn't
 * read as a bare, unstyled camera feed. Pure canvas drawing, not a
 * drawable resource: the cutout has to be recomputed against whatever
 * size this view actually ends up at.
 */
private class ViewfinderOverlay(context: android.content.Context) : View(context) {
    private val density = context.resources.displayMetrics.density
    private fun dp(value: Int) = value * density

    private val scrimPaint = Paint().apply {
        color = Color.parseColor("#99000000")
    }
    private val cutoutPaint = Paint().apply {
        xfermode = PorterDuffXfermode(PorterDuff.Mode.CLEAR)
        isAntiAlias = true
    }
    private val bracketPaint = Paint().apply {
        color = Color.WHITE
        style = Paint.Style.STROKE
        strokeWidth = dp(3)
        strokeCap = Paint.Cap.ROUND
        isAntiAlias = true
    }

    init {
        setLayerType(LAYER_TYPE_SOFTWARE, null) // required for PorterDuff.Mode.CLEAR to actually punch a hole
    }

    override fun onDraw(canvas: Canvas) {
        val side = kotlin.math.min(width, height) * 0.7f
        val left = (width - side) / 2f
        val top = (height - side) / 2f
        val frame = RectF(left, top, left + side, top + side)
        val cornerRadius = dp(20)

        canvas.drawRect(0f, 0f, width.toFloat(), height.toFloat(), scrimPaint)
        canvas.drawRoundRect(frame, cornerRadius, cornerRadius, cutoutPaint)

        val bracketLen = side * 0.12f
        fun corner(cx: Float, cy: Float, dx: Int, dy: Int) {
            canvas.drawLine(cx, cy, cx + bracketLen * dx, cy, bracketPaint)
            canvas.drawLine(cx, cy, cx, cy + bracketLen * dy, bracketPaint)
        }
        corner(frame.left, frame.top, 1, 1)
        corner(frame.right, frame.top, -1, 1)
        corner(frame.left, frame.bottom, 1, -1)
        corner(frame.right, frame.bottom, -1, -1)
    }
}
