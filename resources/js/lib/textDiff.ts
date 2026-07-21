import { diffLines, type Change } from 'diff';

export type DiffLine = {
    type: 'added' | 'removed' | 'unchanged';
    text: string;
};

export function buildLineDiff(previous: string, current: string): DiffLine[] {
    const changes: Change[] = diffLines(previous ?? '', current ?? '');
    const lines: DiffLine[] = [];

    for (const change of changes) {
        const type: DiffLine['type'] = change.added
            ? 'added'
            : change.removed
              ? 'removed'
              : 'unchanged';

        const parts = change.value.split('\n');
        // diffLines keeps a trailing newline as an empty last segment
        if (parts.length > 0 && parts[parts.length - 1] === '') {
            parts.pop();
        }

        for (const part of parts) {
            lines.push({ type, text: part });
        }
    }

    return lines;
}
