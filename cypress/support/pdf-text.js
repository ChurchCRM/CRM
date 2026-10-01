/**
 * The text FPDF wrote into a PDF, one entry per Tj operator — which is one per
 * line of a Cell()/MultiCell() call, so a label's address comes back line for
 * line. Page content is zlib-compressed by FPDF, so each `stream ... endstream`
 * block is inflated with the browser's DecompressionStream; blocks that are not
 * page content (fonts, images) yield no Tj operators and drop out on their own.
 *
 * @param {string} binaryBody a cy.request() body fetched with encoding "binary"
 * @returns {Promise<string[]>}
 */
export const pdfText = (binaryBody) => {
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

    return Promise.all(blocks.map(inflate)).then((contents) =>
        contents.flatMap((content) =>
            Array.from(content.matchAll(/\(((?:\\.|[^\\)])*)\)\s*Tj/g), ([, text]) =>
                text.replace(/\\([\\()])/g, "$1")
            )
        )
    );
};
