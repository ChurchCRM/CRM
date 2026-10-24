// Computed colours can be rgb(), color(srgb ...), oklch() or oklab(), and Tabler's
// -lt backgrounds are translucent. Resolve every colour through a canvas and
// composite it over what is behind it, so contrast is measured on what is drawn.
const canvas = document.createElement("canvas");
canvas.width = 1;
canvas.height = 1;
const ctx = canvas.getContext("2d", { willReadFrequently: true });

const WHITE = [255, 255, 255];

export function solidColour(css, backdrop = WHITE) {
    ctx.fillStyle = `rgb(${backdrop.join(",")})`;
    ctx.fillRect(0, 0, 1, 1);
    ctx.fillStyle = css;
    ctx.fillRect(0, 0, 1, 1);
    const [r, g, b] = ctx.getImageData(0, 0, 1, 1).data;
    return [r, g, b];
}

export function effectiveBackground(el) {
    const win = el.ownerDocument.defaultView;
    const chain = [];
    for (let node = el; node; node = node.parentElement) {
        chain.unshift(node);
    }
    return chain.reduce((backdrop, node) => solidColour(win.getComputedStyle(node).backgroundColor, backdrop), WHITE);
}

export function relativeLuminance([r, g, b]) {
    const [lr, lg, lb] = [r, g, b].map((v) => {
        const c = v / 255;
        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    });
    return 0.2126 * lr + 0.7152 * lg + 0.0722 * lb;
}

export function contrastRatio(foregroundCss, backgroundRgb) {
    const foreground = solidColour(foregroundCss, backgroundRgb);
    const [hi, lo] = [relativeLuminance(foreground), relativeLuminance(backgroundRgb)].sort((a, b) => b - a);
    return (hi + 0.05) / (lo + 0.05);
}
