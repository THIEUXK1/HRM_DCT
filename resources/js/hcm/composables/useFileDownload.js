import api from '../api/client';

export function useFileDownload() {
    async function downloadApiGet(path, params = {}, defaultName = 'export.dat') {
        const response = await api.get(path, {
            params,
            responseType: 'blob',
        });

        const disposition = response.headers['content-disposition'] || '';
        const match = disposition.match(/filename="?([^"]+)"?/);
        const filename = match?.[1] || defaultName;

        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', filename);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    }

    return { downloadApiGet };
}
