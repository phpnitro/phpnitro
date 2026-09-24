import AVFoundation
import UIKit

/// Real gap found testing VideoPlayer on a physical device: the box
/// drawn before playback starts was always the flat, empty
/// `Tokens.surfaceMuted()` background — no preview of the actual video,
/// even with a real internet connection, because nothing here ever
/// fetched one. There's no separate poster-image asset for a plain
/// `VideoPlayer($url)` call (unlike a real player SDK, which usually
/// takes an explicit poster URL) — one real frame pulled straight out
/// of the video itself, the same "no DOM/UIImageView concept server-side,
/// so let the native layer own it" idiom ImageLoader.swift already uses
/// for a plain `Image()`, just backed by `AVAssetImageGenerator`
/// instead of a raw `URLSession` GET (which would just fail to decode
/// an `.mp4` as a `UIImage`). Same in-memory NSCache/inFlight-dedup
/// shape as ImageLoader.swift, deliberately duplicated rather than
/// shared — the two loaders decode two genuinely different kinds of
/// payload (image bytes vs. one sampled video frame) and forcing one
/// generic "loader" abstraction over both wasn't worth it for a single
/// call site each.
enum VideoThumbnailLoader {
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

        guard let assetURL = URL(string: url) else { return finish(nil) }

        let asset = AVURLAsset(url: assetURL)
        let generator = AVAssetImageGenerator(asset: asset)
        generator.appliesPreferredTrackTransform = true

        // Half a second in, not exactly 0 — a real .mp4's very first
        // frame is often a hard black/blank one before any real content
        // starts (confirmed comparing both against the sample clip this
        // example app actually ships), the same reason most players
        // sample a moment in rather than frame zero for a poster.
        let time = CMTime(seconds: 0.5, preferredTimescale: 600)

        // generateCGImagesAsynchronously(forTimes:), not the iOS-16-only
        // generateCGImageAsynchronously(for:) — this engine's own
        // deployment target is iOS 15 (see Package.swift/buildIosHostApp()'s
        // own `-target arm64-apple-ios15.0`), so the newer API isn't
        // available to every consumer of this framework yet.
        generator.generateCGImagesAsynchronously(forTimes: [NSValue(time: time)]) { _, cgImage, _, _, _ in
            finish(cgImage.map { UIImage(cgImage: $0) })
        }
    }
}
