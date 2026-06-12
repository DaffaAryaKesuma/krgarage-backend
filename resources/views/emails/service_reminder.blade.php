<x-mail::message>
# Halo, {{ $pelanggan->nama }}!

Ini adalah pengingat {{ $tahapPengingat }} untuk servis berkala Vespa Anda di **KRGarage**.

**Model Vespa:** {{ $vespa->model }}  
**Plat Nomor:** {{ strtoupper($vespa->plat_nomor) }}  
**Servis Terakhir:** {{ $vespa->tanggal_servis_terakhir ? \Carbon\Carbon::parse($vespa->tanggal_servis_terakhir)->locale('id')->translatedFormat('d F Y') : 'Belum pernah' }}  
**Jadwal Servis Berikutnya:** {{ $vespa->tanggal_servis_selanjutnya ? \Carbon\Carbon::parse($vespa->tanggal_servis_selanjutnya)->locale('id')->translatedFormat('d F Y') : '-' }}

<x-mail::button url="{{ $urlPemesanan }}" color="primary">
Pesan Servis Sekarang
</x-mail::button>

Jika Anda sudah membuat pemesanan, email berikutnya tidak akan dikirim untuk jadwal ini.

Salam Ngebul,<br>
Tim KRGarage
</x-mail::message>
