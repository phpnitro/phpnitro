package com.phpnitro.go

import android.content.Intent
import android.graphics.Color
import android.os.Bundle
import android.text.InputType
import android.view.Gravity
import android.view.View
import android.widget.Button
import android.widget.EditText
import android.widget.LinearLayout
import android.widget.TextView
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity

/**
 * PhpNitro Go's entire own UI — everything past "Connecter" is
 * NativeRenderPocActivity (from :engine, the exact same class :app's demo
 * uses), pointed at a remote `phpx serve` instead of an embedded php-cli
 * process. This app never bundles a single line of any project's PHP: it's
 * a pure client for whatever `phpx serve` happens to be running on the
 * same network, the same relationship Expo Go has to a Metro dev server.
 *
 * Built as plain Views in code, not an XML layout — this is a single
 * one-shot form, not worth a layout file, and matches how this monorepo
 * already builds its own native dialogs (see NativeRenderPocActivity's
 * showConfirmDialog()-style helpers).
 */
class ConnectActivity : AppCompatActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        val density = resources.displayMetrics.density
        fun dp(value: Int): Int = (value * density).toInt()

        val root = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(dp(32), dp(64), dp(32), dp(32))
        }

        root.addView(
            TextView(this).apply {
                text = "PhpNitro Go"
                textSize = 28f
                setTypeface(typeface, android.graphics.Typeface.BOLD)
            },
        )

        root.addView(
            TextView(this).apply {
                text = "Adresse affichée par `phpx serve` sur la machine de dev, même réseau Wi-Fi."
                textSize = 15f
                setTextColor(Color.DKGRAY)
                setPadding(0, dp(12), 0, dp(24))
            },
        )

        val urlField = EditText(this).apply {
            hint = "192.168.1.23:8090"
            inputType = InputType.TYPE_CLASS_TEXT or InputType.TYPE_TEXT_VARIATION_URI
            setSingleLine()
        }
        root.addView(urlField)

        val errorLabel = TextView(this).apply {
            setTextColor(Color.RED)
            visibility = View.GONE
            setPadding(0, dp(8), 0, 0)
        }
        root.addView(errorLabel)

        val connectButton = Button(this).apply {
            text = "Connecter"
        }
        root.addView(
            connectButton,
            LinearLayout.LayoutParams(LinearLayout.LayoutParams.WRAP_CONTENT, LinearLayout.LayoutParams.WRAP_CONTENT).apply {
                gravity = Gravity.END
                topMargin = dp(20)
            },
        )

        connectButton.setOnClickListener {
            val parsed = parseHostPort(urlField.text.toString())
            if (parsed == null) {
                errorLabel.text = "Format attendu : IP:PORT (ex. 192.168.1.23:8090)"
                errorLabel.visibility = View.VISIBLE
                return@setOnClickListener
            }
            val (host, port) = parsed
            val intent = Intent().apply {
                setClassName(this@ConnectActivity, "com.phpnitro.engine.NativeRenderPocActivity")
                putExtra("serverHost", host)
                putExtra("serverPort", port)
                putExtra("screen", "home")
            }
            try {
                startActivity(intent)
            } catch (e: android.content.ActivityNotFoundException) {
                Toast.makeText(this, "Erreur interne : moteur de rendu introuvable", Toast.LENGTH_LONG).show()
            }
        }

        setContentView(root)
    }

    /**
     * Accepts "192.168.1.23:8090" or "http://192.168.1.23:8090" (people
     * will paste the exact string `phpx serve` prints, which includes the
     * scheme) — strips an optional scheme, then requires exactly one
     * ":<digits>" port suffix.
     */
    private fun parseHostPort(input: String): Pair<String, Int>? {
        val withoutScheme = input.trim().removePrefix("http://").removePrefix("https://").trimEnd('/')
        val colonIndex = withoutScheme.lastIndexOf(':')
        if (colonIndex <= 0 || colonIndex == withoutScheme.length - 1) return null
        val host = withoutScheme.substring(0, colonIndex)
        val port = withoutScheme.substring(colonIndex + 1).toIntOrNull() ?: return null
        if (host.isEmpty() || port !in 1..65535) return null
        return host to port
    }
}
