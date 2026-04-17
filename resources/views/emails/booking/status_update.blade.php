<x-mail::message>
# {{ $judulEmail }}

Halo **{{ $pemesanan->pengguna->nama ?? "Pelanggan KRGarage" }}**,

{{ $pesanEmail }}

Berikut adalah rincian pesanan Anda saat ini:

<x-mail::panel>
**Kode Pesanan:** {{ $pemesanan->kode_pemesanan }}  
**Status Saat Ini:** **{{ $pemesanan->status }}**  
**Tanggal Servis:** {{ \Carbon\Carbon::parse($pemesanan->tanggal_pemesanan)->locale("id")->translatedFormat("l, d F Y") }}  
**Waktu Servis:** {{ \Carbon\Carbon::parse($pemesanan->waktu_pemesanan)->format("H:i") }} WIB  
**Kendaraan:** {{ $pemesanan->vespa->model ?? "Vespa Anda" }} ({{ $pemesanan->vespa->plat_nomor ?? "-" }})  

@if($pemesanan->catatan_pelanggan)
**Catatan Pelanggan:**  
_{{ $pemesanan->catatan_pelanggan }}_
@endif
</x-mail::panel>

Anda dapat memantau riwayat pesanan servis secara langsung melalui dashboard akun pelanggan Anda:

<x-mail::button url="http://localhost:5173/app/riwayat">
Cek Status Pesanan
</x-mail::button>

Terima kasih atas kepercayaannya,<br>
Tim KRGarage
</x-mail::message>
