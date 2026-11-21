/**
 * Share a receipt as an image (Kirim WA).
 *
 * Renders the element to a PNG and opens the phone's share sheet (Web Share API
 * with files), where staff pick WhatsApp. Sharing files needs HTTPS and a
 * supporting browser (Android Chrome, iOS Safari 15+); otherwise the PNG is
 * downloaded instead so it can be sent manually.
 *
 * Returns 'shared' | 'downloaded' | 'cancelled'.
 */
window.shareReceiptImage = async function (element, filename, title) {
    let { toBlob } = await import('html-to-image')

    let options = {
        pixelRatio: Math.min(3, Math.max(2, window.devicePixelRatio || 2)),
        backgroundColor: '#ffffff',
        cacheBust: true,
        // Leave out on-screen-only bits (e.g. copy buttons).
        filter: node => !(node instanceof HTMLElement && node.hasAttribute('data-capture-hide')),
    }

    // Safari sometimes misses images on the first pass; render twice there.
    await toBlob(element, options).catch(() => null)
    let blob = await toBlob(element, options)
    if (!blob) throw new Error('Gagal membuat gambar struk')

    let file = new File([blob], filename, { type: 'image/png' })

    if (navigator.canShare && navigator.canShare({ files: [file] })) {
        try {
            await navigator.share({ files: [file], title })
            return 'shared'
        } catch (error) {
            if (error.name === 'AbortError') return 'cancelled'
            throw error
        }
    }

    let url = URL.createObjectURL(blob)
    let link = Object.assign(document.createElement('a'), { href: url, download: filename })
    document.body.appendChild(link)
    link.click()
    link.remove()
    setTimeout(() => URL.revokeObjectURL(url), 10000)

    return 'downloaded'
}
