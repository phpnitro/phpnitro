import AppKit

// Classic AppKit entry point (a top-level main.swift, not @main on
// AppDelegate) — the standard, unambiguous way to bootstrap NSApplication
// from a Swift Package executable target.
let app = NSApplication.shared
let delegate = AppDelegate()
app.delegate = delegate
app.setActivationPolicy(.regular)
app.run()
