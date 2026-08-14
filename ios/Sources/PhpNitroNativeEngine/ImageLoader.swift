import UIKit

/// The iOS counterpart of android/engine's ImageLoader.kt — no
/// Kingfisher/SDWebImage dependency added just for this. An in-memory
/// NSCache (evicted under memory pressure automatically, unlike a plain
/// dictionary) keyed by URL, one in-flight fetch per distinct URL
/// (deduped so a fast re-render doesn't refetch), completion posted back
/// to the main thread — same shape as the Kotlin original, `URLSession`
/// standing in for `HttpURLConnection` and a lock-guarded `Set` standing
/// in for Kotlin's `synchronized(inFlight)`.
enum ImageLoader {
    private static let cache = NSCache<NSString, UIImage>()
    private static var inFlight = Set<String>()
    private static let inFlightLock = NSLock()

    static func get(_ url: String) -> UIImage? {
        cache.object(forKey: url as NSString)
    }

    static func load(_ url: String, onLoaded: @escaping () -> Void) {
        if cache.object(forKey: url as NSString) != nil { return }

        inFlightLock.lock()
        let alreadyLoading = !inFlight.insert(url).inserted
        inFlightLock.unlock()
        if alreadyLoading { return }

        func finish(_ image: UIImage?) {
            inFlightLock.lock()
            inFlight.remove(url)
            inFlightLock.unlock()

            guard let image else { return }
            cache.setObject(image, forKey: url as NSString)
            DispatchQueue.main.async(execute: onLoaded)
        }

        // A camera-captured or gallery-picked image (see
        // NativeDeviceBridge.kt's capturePhoto()/pickImage() on the
        // Android side) comes back as a base64 `data:` URI, not a real
        // network location — decode it directly instead of a doomed
        // URLSession fetch.
        if url.hasPrefix("data:") {
            DispatchQueue.global(qos: .utility).async {
                let base64Payload = url.split(separator: ",", maxSplits: 1).last.map(String.init) ?? ""
                guard let data = Data(base64Encoded: base64Payload) else { return finish(nil) }
                finish(UIImage(data: data))
            }
            return
        }

        guard let requestURL = URL(string: url) else { return finish(nil) }
        var request = URLRequest(url: requestURL)
        request.timeoutInterval = 8

        URLSession.shared.dataTask(with: request) { data, _, _ in
            finish(data.flatMap(UIImage.init(data:)))
        }.resume()
    }
}
