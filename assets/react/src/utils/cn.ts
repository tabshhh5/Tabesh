/**
 * Class name utility (similar to clsx)
 */

type ClassValue = string | number | boolean | undefined | null | ClassValue[]

export const cn = (...classes: ClassValue[]): string => {
  return classes
    .flat()
    .filter((c) => typeof c === 'string' && c.length > 0)
    .join(' ')
}
