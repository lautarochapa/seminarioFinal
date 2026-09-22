import { parseDateOnly, parseDecimal } from '../src/utils/formValues';
import { formatDate } from '../src/utils/retail';

it.each(['2026-99-99', '2026-02-30', '2025-02-29', '22/09/2026', ''])('rejects invalid date %s', (value) => {
  expect(parseDateOnly(value)).toBeNull();
});
it('keeps calendar dates in local time instead of the previous day', () => {
  const date = parseDateOnly('2026-09-23')!;
  expect([date.getFullYear(), date.getMonth(), date.getDate(), date.getHours()]).toEqual([2026, 8, 23, 0]);
  expect(formatDate('2026-09-23')).toMatch(/23.*9.*2026/);
  expect(parseDateOnly('2024-02-29')?.getDate()).toBe(29);
});
it.each([['12,50', 12.5], ['12.50', 12.5], ['0', 0], ['-1', -1], [' 2 ', 2]])('parses %s', (value, expected) => {
  expect(parseDecimal(String(value))).toBe(expected);
});
it.each(['', 'Infinity', 'NaN', '1,000.25', '1.2.3', '--', '1e4'])('rejects ambiguous decimal %s', (value) => {
  expect(parseDecimal(value)).toBeNull();
});
