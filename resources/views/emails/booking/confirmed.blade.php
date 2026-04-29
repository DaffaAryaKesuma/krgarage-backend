<x-mail::message>
# Halo, {{ $pemesanan->pengguna->nama }}!

Terima kasih telah melakukan pemesanan servis di **KRGarage**. 
Pesanan Anda telah berhasil kami terima. Berikut adalah rincian jadwal Anda:

**Detail Kendaraan:** {{ $pemesanan->vespa->model ?? 'Vespa' }} ({{ $pemesanan->vespa->plat_nomor ?? '-' }})  
**Tanggal Datang:** {{ \Carbon\Carbon::parse($pemesanan->tanggal_pemesanan)->locale('id')->translatedFormat('l, d F Y') }}  
**Tujuan Servis:** {{ $pemesanan->layanan->pluck('nama_layanan')->join(', ') }}  
**Catatan:**  {{ $pemesanan->catatan_pelanggan ?? 'Tidak ada catatan tambahan' }}

<x-mail::button url="http://localhost:5173/app/riwayat/{{ $pemesanan->id }}" color="primary">
Cek Status Pesanan
</x-mail::button>

Mohon datang sesuai jadwal yang telah ditentukan ya.

Salam Ngebul,<br>
Tim KRGarage
</x-mail::message>