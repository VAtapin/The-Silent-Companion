import Alpine from 'alpinejs';

window.Alpine = Alpine;

const translateInterface = () => {
    const translations = window.interfaceTranslations || {};
    if (!Object.keys(translations).length) return;
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    const nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);
    nodes.forEach((node) => {
        if (['SCRIPT', 'STYLE', 'TEXTAREA'].includes(node.parentElement?.tagName) || node.parentElement?.closest('[data-no-ui-translate]')) return;
        const match = node.nodeValue.match(/^(\s*)(.*?)(\s*)$/s);
        if (match && translations[match[2]]) node.nodeValue = match[1] + translations[match[2]] + match[3];
    });
    document.querySelectorAll('input[placeholder], textarea[placeholder]').forEach((element) => {
        if (translations[element.placeholder]) element.placeholder = translations[element.placeholder];
    });
};

document.addEventListener('DOMContentLoaded', translateInterface);

Alpine.data('posterUpload', (maxBytes) => ({
    preview: null,
    fileName: '',
    fileSize: '',
    error: '',
    processing: false,

    async select(file) {
        if (!file) return;
        this.error = '';
        this.processing = true;

        try {
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                throw new Error('Выберите изображение JPG, PNG или WEBP.');
            }

            let prepared = file;
            if (file.size > maxBytes * 0.8 || file.type === 'image/png') {
                prepared = await this.compress(file);
            }
            if (prepared.size > maxBytes) {
                throw new Error(`После обработки файл всё ещё больше лимита ${this.format(maxBytes)}.`);
            }

            const transfer = new DataTransfer();
            transfer.items.add(prepared);
            this.$refs.file.files = transfer.files;
            if (this.preview) URL.revokeObjectURL(this.preview);
            this.preview = URL.createObjectURL(prepared);
            this.fileName = prepared.name;
            this.fileSize = this.format(prepared.size);
        } catch (error) {
            this.clear();
            this.error = error.message || 'Не удалось подготовить изображение.';
        } finally {
            this.processing = false;
        }
    },

    async compress(file) {
        const bitmap = await createImageBitmap(file);
        const scale = Math.min(1, 2560 / bitmap.width, 1440 / bitmap.height);
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(bitmap.width * scale));
        canvas.height = Math.max(1, Math.round(bitmap.height * scale));
        canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        bitmap.close();
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.88));
        if (!blob) throw new Error('Браузер не смог сжать изображение.');
        return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', {type: 'image/jpeg', lastModified: Date.now()});
    },

    clear() {
        if (this.preview) URL.revokeObjectURL(this.preview);
        this.preview = null;
        this.fileName = '';
        this.fileSize = '';
        if (this.$refs.file) this.$refs.file.value = '';
    },

    format(bytes) {
        return bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} МБ` : `${Math.ceil(bytes / 1024)} КБ`;
    },
}));

Alpine.start();
