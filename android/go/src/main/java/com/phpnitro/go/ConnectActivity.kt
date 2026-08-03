package com.phpnitro.go

import android.content.Intent
import android.graphics.Color
import android.graphics.Typeface
import android.graphics.drawable.GradientDrawable
import android.os.Bundle
import android.text.InputType
import android.view.Gravity
import android.view.View
import android.widget.EditText
import android.widget.FrameLayout
import android.widget.LinearLayout
import android.widget.ScrollView
import android.widget.TextView
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity

/**
 * PhpNitro Go's entry screen — everything past "Connecter"/a successful
 * scan is NativeRenderPocActivity (from :engine, the exact same class
 * :app's demo uses), pointed at a remote `phpx serve` instead of an
 * embedded php-cli process. This app never bundles a single line of any
 * project's PHP: it's a pure client for whatever `phpx serve` happens to
 * be running on the same network, the same relationship Expo Go has to a
 * Metro dev server.
 *
 * Built as plain Views in code, not an XML layout — this is a single
 * one-shot form, not worth a layout file, and matches how this monorepo
 * already builds its own native dialogs (see NativeRenderPocActivity's
 * showConfirmDialog()-style helpers). The orange-to-red gradient matches
 * this module's own launcher icon (see res/drawable/ic_launcher_background.xml)
 * so the app has one consistent identity from the home screen icon in.
 */
class ConnectActivity : AppCompatActivity() {
    private val gradientStart = Color.parseColor("#F97316")
    private val gradientEnd = Color.parseColor("#DC2626")

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        val density = resources.displayMetrics.density
        fun dp(value: Int): Int = (value * density).toInt()

        val root = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setBackgroundColor(Color.parseColor("#F9FAFB"))
        }

        root.addView(buildHero(::dp))

        val content = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(dp(28), dp(28), dp(28), dp(28))
        }

        val scanButton = TextView(this).apply {
            text = "📷  Scanner un QR code"
            textSize = 16f
            setTypeface(typeface, Typeface.BOLD)
            setTextColor(Color.WHITE)
            gravity = Gravity.CENTER
            setPadding(0, dp(18), 0, dp(18))
            background = GradientDrawable().apply {
                orientation = GradientDrawable.Orientation.LEFT_RIGHT
                colors = intArrayOf(gradientStart, gradientEnd)
                cornerRadius = dp(14).toFloat()
            }
            isClickable = true
            elevation = dp(2).toFloat()
        }
        content.addView(scanButton, LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, LinearLayout.LayoutParams.WRAP_CONTENT))
        scanButton.setOnClickListener {
            startActivity(Intent(this, ScanActivity::class.java))
        }

        content.addView(buildDivider(::dp))

        val fieldLabel = TextView(this).apply {
            text = "ADRESSE DU SERVEUR"
            textSize = 12f
            setTypeface(typeface, Typeface.BOLD)
            setTextColor(Color.parseColor("#9CA3AF"))
            letterSpacing = 0.08f
            setPadding(0, 0, 0, dp(8))
        }
        content.addView(fieldLabel)

        val fieldBox = LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
            gravity = Gravity.CENTER_VERTICAL
            setPadding(dp(16), dp(4), dp(16), dp(4))
            background = GradientDrawable().apply {
                setColor(Color.WHITE)
                setStroke(dp(1), Color.parseColor("#D1D5DB"))
                cornerRadius = dp(12).toFloat()
            }
        }
        val fieldIcon = TextView(this).apply {
            text = "🌐"
            textSize = 16f
            setPadding(0, 0, dp(10), 0)
        }
        val urlField = EditText(this).apply {
            hint = "192.168.1.23:8090"
            setHintTextColor(Color.parseColor("#9CA3AF"))
            setTextColor(Color.parseColor("#111827"))
            inputType = InputType.TYPE_CLASS_TEXT or InputType.TYPE_TEXT_VARIATION_URI
            setSingleLine()
            background = null
        }
        fieldBox.addView(fieldIcon)
        fieldBox.addView(urlField, LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1f))
        content.addView(fieldBox, LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, LinearLayout.LayoutParams.WRAP_CONTENT))

        val errorLabel = TextView(this).apply {
            setTextColor(Color.parseColor("#DC2626"))
            textSize = 13f
            visibility = View.GONE
            setPadding(dp(4), dp(10), dp(4), 0)
        }
        content.addView(errorLabel)

        val connectButton = TextView(this).apply {
            text = "Connecter"
            textSize = 15f
            setTypeface(typeface, Typeface.BOLD)
            setTextColor(gradientEnd)
            gravity = Gravity.CENTER
            setPadding(0, dp(16), 0, dp(16))
            background = GradientDrawable().apply {
                setColor(Color.TRANSPARENT)
                setStroke(dp(2), gradientEnd)
                cornerRadius = dp(14).toFloat()
            }
            isClickable = true
        }
        content.addView(
            connectButton,
            LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, LinearLayout.LayoutParams.WRAP_CONTENT).apply {
                topMargin = dp(16)
            },
        )

        connectButton.setOnClickListener {
            val parsed = parseHostPort(urlField.text.toString())
            if (parsed == null) {
                errorLabel.text = "Format attendu : IP:PORT (ex. 192.168.1.23:8090)"
                errorLabel.visibility = View.VISIBLE
                return@setOnClickListener
            }
            errorLabel.visibility = View.GONE
            val (host, port) = parsed
            try {
                startActivity(renderIntent(this, host, port))
            } catch (e: android.content.ActivityNotFoundException) {
                Toast.makeText(this, "Erreur interne : moteur de rendu introuvable", Toast.LENGTH_LONG).show()
            }
        }

        content.addView(
            TextView(this).apply {
                text = "Assure-toi que ce téléphone et ta machine de dev sont sur le même réseau Wi-Fi, et que `phpx serve` tourne."
                textSize = 12f
                setTextColor(Color.parseColor("#9CA3AF"))
                gravity = Gravity.CENTER
                setPadding(dp(8), dp(28), dp(8), 0)
            },
        )

        root.addView(content)

        val scroll = ScrollView(this).apply { addView(root) }
        setContentView(scroll)
    }

    private fun buildHero(dp: (Int) -> Int): View {
        val hero = FrameLayout(this).apply {
            background = GradientDrawable().apply {
                orientation = GradientDrawable.Orientation.TL_BR
                colors = intArrayOf(gradientStart, gradientEnd)
                cornerRadii = floatArrayOf(0f, 0f, 0f, 0f, dp(28).toFloat(), dp(28).toFloat(), dp(28).toFloat(), dp(28).toFloat())
            }
        }

        val inner = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            gravity = Gravity.CENTER_HORIZONTAL
            setPadding(dp(28), dp(72), dp(28), dp(40))
        }

        val iconBadge = TextView(this).apply {
            text = "⚡"
            textSize = 30f
            gravity = Gravity.CENTER
            background = GradientDrawable().apply {
                shape = GradientDrawable.OVAL
                setColor(Color.parseColor("#33FFFFFF"))
            }
        }
        inner.addView(iconBadge, LinearLayout.LayoutParams(dp(64), dp(64)))

        inner.addView(
            TextView(this).apply {
                text = "PhpNitro Go"
                textSize = 26f
                setTypeface(typeface, Typeface.BOLD)
                setTextColor(Color.WHITE)
                gravity = Gravity.CENTER
                setPadding(0, dp(16), 0, dp(6))
            },
        )
        inner.addView(
            TextView(this).apply {
                text = "Visualise ton projet PhpNitro en direct, sans jamais recompiler l'app."
                textSize = 14f
                setTextColor(Color.parseColor("#FFE4D6"))
                gravity = Gravity.CENTER
            },
        )

        hero.addView(inner, FrameLayout.LayoutParams(FrameLayout.LayoutParams.MATCH_PARENT, FrameLayout.LayoutParams.WRAP_CONTENT))
        return hero
    }

    private fun buildDivider(dp: (Int) -> Int): View {
        val row = LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
            gravity = Gravity.CENTER_VERTICAL
            setPadding(0, dp(24), 0, dp(24))
        }
        fun line() = View(this).apply { setBackgroundColor(Color.parseColor("#E5E7EB")) }
        row.addView(line(), LinearLayout.LayoutParams(0, dp(1), 1f))
        row.addView(
            TextView(this).apply {
                text = "ou saisis l'adresse à la main"
                textSize = 12f
                setTextColor(Color.parseColor("#9CA3AF"))
                setPadding(dp(12), 0, dp(12), 0)
            },
        )
        row.addView(line(), LinearLayout.LayoutParams(0, dp(1), 1f))
        return row
    }
}
