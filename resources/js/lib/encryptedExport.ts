export type EncryptedExportPayload = Record<string, string>;

export type EncryptedExportMessages = {
    invalid: string;
    error: string;
};

export type EncryptedExportResult =
    { ok: true; filename: string } | { ok: false; message: string };

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

function parseFilename(contentDisposition: string | null): string | null {
    if (!contentDisposition) {
        return null;
    }

    const utfMatch = contentDisposition.match(/filename\*=UTF-8''([^;]+)/i);

    if (utfMatch?.[1]) {
        return decodeURIComponent(utfMatch[1]);
    }

    const match = contentDisposition.match(/filename="?([^";]+)"?/i);

    return match?.[1] ?? null;
}

export async function downloadEncryptedExport(
    url: string,
    password: string,
    passwordConfirmation: string,
    extraData: EncryptedExportPayload = {},
    messages: EncryptedExportMessages = {
        invalid: 'Invalid export data.',
        error: 'Error exporting the file.',
    },
): Promise<EncryptedExportResult> {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/octet-stream',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': getCsrfToken(),
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            password,
            password_confirmation: passwordConfirmation,
            ...extraData,
        }),
    });

    if (!response.ok) {
        if (response.status === 422) {
            const data = (await response.json()) as {
                errors?: Record<string, string[]>;
            };
            const firstError = Object.values(data.errors ?? {})
                .flat()
                .find(Boolean);

            return {
                ok: false,
                message: firstError ?? messages.invalid,
            };
        }

        return {
            ok: false,
            message: messages.error,
        };
    }

    const blob = await response.blob();
    const filename =
        parseFilename(response.headers.get('Content-Disposition')) ??
        'export.7z';
    const objectUrl = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = objectUrl;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(objectUrl);

    return { ok: true, filename };
}
