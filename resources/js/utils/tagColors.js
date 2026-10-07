export function tagColors(color) {
    const background = /^#[0-9a-f]{6}$/i.test(color ?? '') ? color : '#e2e8f0';
    const channels = background.slice(1).match(/.{2}/g).map(channel => {
        const value = parseInt(channel, 16) / 255;
        return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
    });
    const luminance = channels[0] * 0.2126 + channels[1] * 0.7152 + channels[2] * 0.0722;
    const darkText = (luminance + 0.05) / 0.05 >= 1.05 / (luminance + 0.05);

    return { background, foreground: darkText ? '#000000' : '#ffffff', darkText };
}
