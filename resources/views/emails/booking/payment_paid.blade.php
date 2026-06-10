<x-mail::message>
# Pembayaran Lunas

Halo **{{ $pemesanan->pengguna->nama ?? "Pelanggan KRGarage" }}**,

Pembayaran untuk pemesanan Anda telah kami terima dan dinyatakan **LUNAS**.

<x-mail::panel>
**Kode Pesanan:** {{ $pemesanan->kode_pemesanan }}  
**Status Pemesanan:** {{ $pemesanan->status }}  
**Status Pembayaran:** **{{ $pemesanan->status_pembayaran }}**  
**Tanggal Pembayaran:** {{ $pemesanan->paid_at ? $pemesanan->paid_at->locale("id")->translatedFormat("l, d F Y H:i") . " WIB" : "-" }}  
**Vespa:** {{ $pemesanan->vespa->model ?? "Vespa Anda" }} ({{ strtoupper($pemesanan->vespa->plat_nomor ?? "-") }})
</x-mail::panel>

## Rincian Layanan

@forelse($pemesanan->layanan as $layanan)
- {{ $layanan->nama_layanan }}: Rp{{ number_format((float) ($layanan->pivot->harga_saat_pesan ?? $layanan->harga ?? 0), 0, ",", ".") }}
@empty
- Tidak ada layanan tercatat.
@endforelse

## Rincian Suku Cadang

@forelse($pemesanan->itemPemesanan as $item)
@php
    $namaSukuCadang = $item->nama_suku_cadang_saat_ini ?? $item->sukuCadang->nama_suku_cadang ?? "Suku cadang";
    $hargaSatuan = (float) ($item->harga_saat_ini ?? 0);
    $jumlah = (int) ($item->jumlah ?? 0);
    $subtotal = $hargaSatuan * $jumlah;
@endphp
- {{ $namaSukuCadang }} x {{ $jumlah }}: Rp{{ number_format($subtotal, 0, ",", ".") }}  
  Harga satuan: Rp{{ number_format($hargaSatuan, 0, ",", ".") }}
@empty
- Tidak ada suku cadang tambahan.
@endforelse

<x-mail::panel>
**Total Biaya:** **Rp{{ number_format((float) ($pemesanan->total_harga ?? 0), 0, ",", ".") }}**  
**Status Pembayaran:** **LUNAS**
</x-mail::panel>

<x-mail::button url="{{ rtrim(config('app.frontend_url'), '/') }}/app/riwayat/{{ $pemesanan->id }}" color="primary">
Lihat Riwayat Pesanan
</x-mail::button>

Terima kasih atas kepercayaannya,<br>
Tim KRGarage
</x-mail::message>
