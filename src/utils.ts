export function parseDateScore(date: string | null): number {
  if (!date) return 0;
  const d = date.toLowerCase();
  let year = 0;
  const ym = d.match(/(\d{4})/);
  if (ym) year = parseInt(ym[1]);
  let month = 6; // default mid-year
  const months: Record<string, number> = {
    janvier: 1, février: 2, mars: 3, avril: 4, mai: 5, juin: 6,
    juillet: 7, août: 8, septembre: 9, octobre: 10, novembre: 11, décembre: 12,
    printemps: 4, été: 7, automne: 10, hiver: 1,
  };
  for (const [key, val] of Object.entries(months)) {
    if (d.includes(key)) { month = val; break; }
  }
  return year * 100 + month;
}
