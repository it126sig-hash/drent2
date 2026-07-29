/**
 * Subtotal sewa mobil untuk mode non all-in.
 * `harga_mobil` per periode (harian/mingguan/bulanan), `diskon_mobil` adalah
 * total diskon untuk baris sewa tersebut (tidak dikalikan durasi).
 */
export function calcRentalSubtotal({ harga_mobil, diskon_mobil, lama_sewa } = {}) {
  const duration = lama_sewa || 1;
  return Math.max(0, (harga_mobil || 0) * duration - (diskon_mobil || 0));
}
