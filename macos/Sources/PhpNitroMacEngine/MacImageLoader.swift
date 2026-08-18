import AppKit

/// The macOS counterpart of ImageLoader.swift (iOS) — identical shape,
/// NSImage/NSCache standing in for UIImage/NSCache (NSCache itself is
/// already cross-platform Foundation, no change needed there at all).
enum MacImageLoader {
    private static let cache = NSCache<NSString, NSImage>()
    private static var inFlight = Set<String>()
    private static let inFlightLock = NSLock()

    static func get(_ url: String) -> NSImage? {
        cache.object(forKey: url as NSString)
    }

    static func load(_ url: String, onLoaded: @escaping () -> Void) {
        if cache.object(forKey: url as NSString) != nil { return }

        inFlightLock.lock()
        let alreadyLoading = !inFlight.insert(url).inserted
        inFlightLock.unlock()
        if alreadyLoading { return }

        func finish(_ image: NSImage?) {
            inFlightLock.lock()
            inFlight.remove(url)
            inFlightLock.unlock()

            guard let image else { return }
            cache.setObject(image, forKey: url as NSString)
            DispatchQueue.main.async(execute: onLoaded)
        }

        if url.hasPrefix("data:") {
            DispatchQueue.global(qos: .utility).async {
                let base64Payload = url.split(separator: ",", maxSplits: 1).last.map(String.init) ?? ""
                guard let data = Data(base64Encoded: base64Payload) else { return finish(nil) }
                finish(NSImage(data: data))
            }
            return
        }

        guard let requestURL = URL(string: url) else { return finish(nil) }
        var request = URLRequest(url: requestURL)
        request.timeoutInterval = 8

        URLSession.shared.dataTask(with: request) { data, _, _ in
            finish(data.flatMap(NSImage.init(data:)))
        }.resume()
    }
}
