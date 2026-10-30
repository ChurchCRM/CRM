/**
 * Every stream in a PDF, inflated. Page content is zlib-compressed by FPDF, so
 * each `stream ... endstream` block is inflated with the browser's
 * DecompressionStream; blocks that are not page content (fonts, images) match
 * none of the drawing operators below and drop out on their own.
 *
 * @param {string} binaryBody a cy.request() body fetched with encoding "binary"
 * @returns {Promise<string[]>}
 */
const streamContents = (binaryBody) => {
    const bytes = Uint8Array.from(binaryBody, (c) => c.charCodeAt(0));
    const decoder = new TextDecoder("latin1");
    const blocks = [];
    const streamStart = /stream\r?\n/g;
    let match;
    while ((match = streamStart.exec(binaryBody)) !== null) {
        const dict = binaryBody.slice(Math.max(0, match.index - 200), match.index);
        const length = /\/Length (\d+)/.exec(dict);
        if (length === null) {
            continue;
        }
        const start = match.index + match[0].length;
        const end = start + Number(length[1]);
        blocks.push({ data: bytes.subarray(start, end), compressed: /FlateDecode/.test(dict) });
        streamStart.lastIndex = end;
    }

    const inflate = async ({ data, compressed }) => {
        if (!compressed) {
            return decoder.decode(data);
        }
        try {
            const inflated = new Blob([data]).stream().pipeThrough(new DecompressionStream("deflate"));
            return decoder.decode(await new Response(inflated).arrayBuffer());
        } catch (error) {
            return "";
        }
    };

    return Promise.all(blocks.map(inflate));
};

const unescapeText = (text) => text.replace(/\\([\\()])/g, "$1");

// FPDF writes Latin-1. mPDF in utf-8 mode writes UTF-16BE, and letter-spacing
// splits one line across a TJ array.
const decodePdfText = (text) => {
    const value = unescapeText(text);
    if (value.length < 2 || value.length % 2 !== 0) {
        return value;
    }
    const pairs = value.length / 2;
    let utf16 = 0;
    for (let i = 0; i < value.length; i += 2) {
        if (value.charCodeAt(i) < 0x20) {
            utf16++;
        }
    }
    if (utf16 / pairs < 0.8) {
        return value;
    }
    let out = "";
    const start = value.charCodeAt(0) === 0xfe && value.charCodeAt(1) === 0xff ? 2 : 0;
    for (let i = start; i < value.length; i += 2) {
        out += String.fromCharCode((value.charCodeAt(i) << 8) | value.charCodeAt(i + 1));
    }
    return out;
};

const TEXT_OPERATOR = /\[((?:\\.|[^\\\]])*)\]\s*TJ|\(((?:\\.|[^\\)])*)\)\s*Tj/g;

const textLines = (content) => {
    const lines = [];
    for (const match of content.matchAll(TEXT_OPERATOR)) {
        if (match[1] !== undefined) {
            const line = Array.from(match[1].matchAll(/\(((?:\\.|[^\\)])*)\)/g), ([, text]) => decodePdfText(text)).join("");
            if (line !== "") {
                lines.push(line);
            }
        } else {
            lines.push(decodePdfText(match[2]));
        }
    }
    return lines;
};

/**
 * Text drawn into a PDF, one entry per Tj or TJ run. An FPDF Cell() is one
 * entry. An mPDF line is one entry, including a letter-spaced heading.
 *
 * @param {string} binaryBody a cy.request() body fetched with encoding "binary"
 * @returns {Promise<string[]>}
 */
export const pdfText = (binaryBody) =>
    streamContents(binaryBody).then((contents) => contents.flatMap(textLines));

/**
 * Where FPDF drew each line of text and each image, in drawing order, in PDF
 * points from the bottom left of the page: a line as `{ text, x, y }` at its
 * baseline, an image as `{ image: true, x, y, width, height }` from its bottom
 * left corner. A booklet sheet shifts its right-hand page across, so compare
 * x only between items on the same page.
 *
 * @param {string} binaryBody a cy.request() body fetched with encoding "binary"
 * @returns {Promise<Array<object>>}
 */
export const pdfDrawing = (binaryBody) =>
    streamContents(binaryBody).then((contents) =>
        contents.flatMap((content) =>
            Array.from(
                content.matchAll(
                    /BT ([\d.]+) ([\d.]+) Td \(((?:\\.|[^\\)])*)\) Tj|q ([\d.]+) 0 0 ([\d.]+) ([\d.]+) ([\d.]+) cm \/I\d+ Do/g,
                ),
                ([, x, y, text, width, height, imageX, imageY]) =>
                    text === undefined
                        ? { image: true, x: Number(imageX), y: Number(imageY), width: Number(width), height: Number(height) }
                        : { text: unescapeText(text), x: Number(x), y: Number(y) },
            ),
        ),
    );
