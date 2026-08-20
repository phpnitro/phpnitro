//! Decodes `image` commands whose `url` is an inline
//! `data:image/png;base64,...` payload — the one image source this crate
//! can rasterize with zero new dependencies (`tiny_skia::Pixmap` already
//! links `png` as part of its default `png-format` feature, see
//! `raster.rs`'s own `draw_image`).
//!
//! `https://` URLs and any non-PNG `data:` URI (JPEG, WebP, ...) are a
//! deliberate, scoped-out gap, not an oversight: a real network fetch and
//! a JPEG/WebP decoder would each need a genuinely new crate this
//! offline-built workspace has no cached copy of. Both silently no-op
//! (same as every unhandled command type already does), matching this
//! command's behavior before this module existed.
//!
//! No `base64` crate is cached offline for this workspace either — the
//! decoder below is hand-rolled, but only ever needs to handle a single
//! well-formed payload already embedded in JSON by whatever produced it
//! (PHP's own `base64_encode()`), not arbitrary/adversarial input.

fn base64_decode_char(byte: u8) -> Option<u8> {
    match byte {
        b'A'..=b'Z' => Some(byte - b'A'),
        b'a'..=b'z' => Some(byte - b'a' + 26),
        b'0'..=b'9' => Some(byte - b'0' + 52),
        b'+' => Some(62),
        b'/' => Some(63),
        _ => None,
    }
}

/// RFC 4648 base64 decode. Returns `None` on any malformed input
/// (invalid character, a real character following padding, more than 2
/// padding characters, or a trailing group that never reached 4
/// characters) rather than guessing at a partial result. Whitespace
/// (space/tab/CR/LF) is skipped, matching most real-world base64
/// producers' line-wrapping.
fn decode_base64(input: &str) -> Option<Vec<u8>> {
    let mut out = Vec::with_capacity(input.len() / 4 * 3);
    let mut group = [0u8; 4];
    let mut group_len = 0usize;
    let mut padding = 0usize;

    for &byte in input.as_bytes() {
        match byte {
            b'\n' | b'\r' | b' ' | b'\t' => continue,
            b'=' => {
                padding += 1;
                if padding > 2 {
                    return None;
                }
                group_len += 1;
            }
            _ => {
                if padding > 0 {
                    return None; // a real character can't follow padding
                }
                group[group_len] = base64_decode_char(byte)?;
                group_len += 1;
            }
        }

        if group_len == 4 {
            out.push((group[0] << 2) | (group[1] >> 4));
            if padding < 2 {
                out.push((group[1] << 4) | (group[2] >> 2));
            }
            if padding < 1 {
                out.push((group[2] << 6) | group[3]);
            }
            group_len = 0;
        }
    }

    if group_len != 0 {
        return None; // incomplete trailing group
    }
    Some(out)
}

/// Decodes a `data:image/png;base64,<payload>` URL into raw PNG bytes —
/// `None` for any other scheme/encoding (see this module's own doc
/// comment for why that's a deliberate gap) or malformed base64.
pub fn decode_data_uri_png(url: &str) -> Option<Vec<u8>> {
    let payload = url.strip_prefix("data:image/png;base64,")?;
    decode_base64(payload)
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn decodes_a_known_short_string() {
        // "Man" -> "TWFu", the textbook base64 round-trip example.
        assert_eq!(decode_base64("TWFu"), Some(b"Man".to_vec()));
    }

    #[test]
    fn decodes_with_one_and_two_padding_characters() {
        assert_eq!(decode_base64("TWE="), Some(b"Ma".to_vec()));
        assert_eq!(decode_base64("TQ=="), Some(b"M".to_vec()));
    }

    #[test]
    fn rejects_a_real_character_following_padding() {
        assert_eq!(decode_base64("TQ=Q"), None);
    }

    #[test]
    fn rejects_an_incomplete_trailing_group() {
        assert_eq!(decode_base64("TWFuTW"), None);
    }

    #[test]
    fn rejects_an_invalid_character() {
        assert_eq!(decode_base64("TWF!"), None);
    }

    #[test]
    fn non_data_uri_and_non_png_data_uri_both_return_none() {
        assert_eq!(decode_data_uri_png("https://example.com/photo.jpg"), None);
        assert_eq!(decode_data_uri_png("data:image/jpeg;base64,/9j/4AAQ"), None);
    }

    #[test]
    fn decodes_the_real_golden_fixtures_1x1_png_and_it_starts_with_the_png_magic_bytes() {
        // The exact data URI in packages/ui/tests/Golden/__fixtures__/
        // image_network_and_data_uri.json — a real payload PHP's own
        // base64_encode() produced, not hand-authored for this test.
        let url = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=";
        let bytes = decode_data_uri_png(url).expect("real fixture payload must decode");
        assert_eq!(&bytes[0..8], &[0x89, 0x50, 0x4E, 0x47, 0x0D, 0x0A, 0x1A, 0x0A], "PNG magic bytes");
    }
}
