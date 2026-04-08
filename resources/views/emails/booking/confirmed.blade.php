<x-mail::message>
# Halo, {{ $booking->pengguna->nama }}!

Terima kasih telah melakukan pemesanan servis di **KRGarage**. 
Pesanan Anda telah berhasil kami terima. Berikut adalah rincian jadwal Anda:

**Detail Kendaraan:** {{ $booking->vespa->model ?? 'Vespa' }} ({{ $booking->vespa->plat_nomor ?? '-' }})  
**Tanggal Datang:** {{ \Carbon\Carbon::parse($booking->tanggal_pemesanan)->locale('id')->translatedFormat('l, d F Y') }}  
**Tujuan Servis:** {{ $booking->layanan->pluck('nama_layanan')->join(', ') }}  
**Catatan:**  {{ $booking->catatan_pelanggan ?? 'Tidak ada catatan tambahan' }}

<x-mail::button :url="'http://localhost:5173/app/dashboard'">
Cek Status Pesanan
</x-mail::button>

Mohon datang sesuai jadwal yang telah ditentukan ya.

Salam Ngebul,<br>
Tim KRGarage
</x-mail::message>