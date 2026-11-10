export function formatNumber(value: string | number): string {
    return String(value).replace(/^-?\d+/, (integer) =>
        integer.replace(/\B(?=(?:\d{3})+$)/g, ','),
    );
}
