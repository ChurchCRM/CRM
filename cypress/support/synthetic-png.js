/**
 * Synthetic PNG builder for the upload-limit specs.
 *
 * Builds a valid PNG of any dimensions from almost no bytes: 1-bit greyscale,
 * every pixel black, so the raw scanline stream is all zeros and deflates to
 * roughly a thousandth of its size. A 12000x12000 image comes out under
 * 20 KB, which sails through the 2 MB upload limit while carrying 144 million
 * source pixels: exactly the shape of file the decode pixel budget in
 * ImageSupportUtils::assertWithinDecodeBudget() exists to refuse before GD
 * allocates anything.
 *
 * Compression uses the browser's CompressionStream (Chromium 80+, so every
 * browser Cypress drives), which is why buildBlankPng() returns a Promise:
 * hand it to cy.wrap() or call it inside a .then().
 */

const CRC_TABLE = (() => {
    const table = new Uint32Array(256);
    for (let n = 0; n < 256; n++) {
        let c = n;
        for (let k = 0; k < 8; k++) {
            c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
        }
        table[n] = c >>> 0;
    }
    return table;
})();

function crc32(bytes) {
    let crc = 0xffffffff;
    for (const byte of bytes) {
        crc = CRC_TABLE[(crc ^ byte) & 0xff] ^ (crc >>> 8);
    }
    return (crc ^ 0xffffffff) >>> 0;
}

function be32(value) {
    return [
        (value >>> 24) & 0xff,
        (value >>> 16) & 0xff,
        (value >>> 8) & 0xff,
        value & 0xff,
    ];
}

function concat(parts) {
    const total = parts.reduce((sum, part) => sum + part.length, 0);
    const out = new Uint8Array(total);
    let offset = 0;
    for (const part of parts) {
        out.set(part, offset);
        offset += part.length;
    }
    return out;
}

/** One PNG chunk: length, type, data, CRC over type + data. */
function chunk(type, data) {
    const typeBytes = Uint8Array.from(type, (ch) => ch.charCodeAt(0));
    const body = concat([typeBytes, data]);
    return concat([
        Uint8Array.from(be32(data.length)),
        body,
        Uint8Array.from(be32(crc32(body))),
    ]);
}

/** zlib-wrapped deflate of `byteCount` zero bytes, streamed 1 MB at a time. */
async function deflateZeros(byteCount) {
    const stream = new CompressionStream("deflate");
    const zeros = new Uint8Array(1024 * 1024);

    const writing = (async () => {
        const writer = stream.writable.getWriter();
        let remaining = byteCount;
        while (remaining > 0) {
            const size = Math.min(remaining, zeros.length);
            await writer.write(zeros.subarray(0, size));
            remaining -= size;
        }
        await writer.close();
    })();

    const parts = [];
    const reader = stream.readable.getReader();
    for (;;) {
        const { value, done } = await reader.read();
        if (done) {
            break;
        }
        parts.push(value);
    }
    await writing;

    return concat(parts);
}

/**
 * A valid `width` x `height` PNG (1-bit greyscale, all black) as a
 * `data:image/png;base64,...` URI ready for an `imgBase64` upload body.
 */
export async function buildBlankPng(width, height) {
    // Each scanline is one filter-type byte plus the packed 1-bit pixels.
    const rowBytes = 1 + Math.ceil(width / 8);
    const idat = await deflateZeros(rowBytes * height);

    // Bit depth 1, colour type 0 (greyscale), deflate, adaptive filter, no interlace.
    const ihdr = Uint8Array.from([...be32(width), ...be32(height), 1, 0, 0, 0, 0]);
    const signature = Uint8Array.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]);

    const png = concat([
        signature,
        chunk("IHDR", ihdr),
        chunk("IDAT", idat),
        chunk("IEND", new Uint8Array(0)),
    ]);

    return `data:image/png;base64,${Cypress.Buffer.from(png).toString("base64")}`;
}
