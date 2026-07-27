import { index as productTasksIndex } from '@/routes/products/tasks';
import {
    createEditReturnBackRule,
    registerReturnBackRule,
    useReturnBack,
} from '@/composables/useReturnBack';

const taskEditPathPattern = /^\/products\/(\d+)\/tasks\/(\d+)\/edit\/?$/;

export function taskEditReturnScope(taskId: number): string {
    return `task-edit-return.${taskId}`;
}

registerReturnBackRule(
    createEditReturnBackRule({
        matchEdit: (pathname) => {
            const match = pathname.match(taskEditPathPattern);

            return match ? taskEditReturnScope(Number(match[2])) : null;
        },
        skipFrom: [/^\/products\/\d+\/tasks\/create\/?$/],
    }),
);

/**
 * Resolves Back target for task Edit: remembered origin, else product tasks index.
 */
export function useTaskEditBack(productId: number, taskId: number) {
    return useReturnBack(
        taskEditReturnScope(taskId),
        () => productTasksIndex(productId).url,
    );
}
