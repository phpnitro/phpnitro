package com.phpnitro.go

import android.content.Context
import android.content.Intent

/** Both ConnectActivity's manual "Connecter" and ScanActivity's decoded QR end up here — same target, same extras. */
fun renderIntent(context: Context, host: String, port: Int): Intent = Intent().apply {
    setClassName(context, "com.phpnitro.engine.NativeRenderPocActivity")
    putExtra("serverHost", host)
    putExtra("serverPort", port)
    putExtra("screen", "home")
}

/**
 * Shared by ConnectActivity's manual entry field and ScanActivity's decoded
 * QR payload — both ultimately need the same "IP:PORT, optionally prefixed
 * with a scheme" parsing, since `phpx serve`'s printed URL and its QR
 * code encode the exact same string (see bin/QrCode.php's caller in
 * cmdServe()).
 */
fun parseHostPort(input: String): Pair<String, Int>? {
    val withoutScheme = input.trim().removePrefix("http://").removePrefix("https://").trimEnd('/')
    val colonIndex = withoutScheme.lastIndexOf(':')
    if (colonIndex <= 0 || colonIndex == withoutScheme.length - 1) return null
    val host = withoutScheme.substring(0, colonIndex)
    val port = withoutScheme.substring(colonIndex + 1).toIntOrNull() ?: return null
    if (host.isEmpty() || port !in 1..65535) return null
    return host to port
}
