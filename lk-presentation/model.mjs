export const cohorts = Object.freeze({free: [100, 70, 42, 25], paid: [40, 36, 26, 20]});
export function cohortValues(kind) {
  if (kind === 'all') return cohorts.free.map((n, i) => n + cohorts.paid[i]);
  return [...cohorts[kind]];
}
export function percentage(n, total) { return total ? Math.round(n / total * 100) : 0; }
export function toggleCompleted(completed, lesson) {
  const next = new Set(completed);
  if (next.has(lesson)) next.delete(lesson); else next.add(lesson);
  return next;
}
