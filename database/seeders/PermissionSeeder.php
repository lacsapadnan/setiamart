<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // // CRUD cabang permissions
        // Permission::create(['name' => 'simpan cabang']);
        // Permission::create(['name' => 'baca cabang']);
        // Permission::create(['name' => 'update cabang']);
        // Permission::create(['name' => 'hapus cabang']);

        // // CRUD produk permissions
        // Permission::create(['name' => 'simpan produk']);
        // Permission::create(['name' => 'baca produk']);
        // Permission::create(['name' => 'update produk']);
        // Permission::create(['name' => 'hapus produk']);
        // Permission::create(['name' => 'import produk']);

        // // CRUD supplier permissions
        // Permission::create(['name' => 'simpan supplier']);
        // Permission::create(['name' => 'baca supplier']);
        // Permission::create(['name' => 'update supplier']);
        // Permission::create(['name' => 'hapus supplier']);
        // Permission::create(['name' => 'import supplier']);

        // // CRUD customer permissions
        // Permission::create(['name' => 'simpan customer']);
        // Permission::create(['name' => 'baca customer']);
        // Permission::create(['name' => 'update customer']);
        // Permission::create(['name' => 'hapus customer']);
        // Permission::create(['name' => 'import customer']);

        // // CRUD unit permissions
        // Permission::create(['name' => 'simpan unit']);
        // Permission::create(['name' => 'baca unit']);
        // Permission::create(['name' => 'update unit']);
        // Permission::create(['name' => 'hapus unit']);

        // CRUD kas permissions
        // Permission::create(['name' => 'simpan kas']);
        // Permission::create(['name' => 'baca kas']);
        // Permission::create(['name' => 'update kas']);
        // Permission::create(['name' => 'hapus kas']);
        // Permission::create(['name' => 'item pendapatan kas']);
        // Permission::create(['name' => 'item pengeluaran kas']);

        // // CRUD inventory permissions
        // Permission::create(['name' => 'simpan inventory']);
        // Permission::create(['name' => 'baca inventory']);
        // Permission::create(['name' => 'update inventory']);
        // Permission::create(['name' => 'hapus inventory']);

        // // CRUD user permissions
        // Permission::create(['name' => 'simpan user']);
        // Permission::create(['name' => 'baca user']);
        // Permission::create(['name' => 'update user']);
        // Permission::create(['name' => 'hapus user']);

        // // CRUD role permissions
        // Permission::create(['name' => 'simpan role']);
        // Permission::create(['name' => 'baca role']);
        // Permission::create(['name' => 'update role']);
        // Permission::create(['name' => 'hapus role']);

        // // CRUD permission permissions
        // Permission::create(['name' => 'simpan permission']);
        // Permission::create(['name' => 'baca permission']);
        // Permission::create(['name' => 'update permission']);
        // Permission::create(['name' => 'hapus permission']);

        // // CRUD pembelian permissions
        // Permission::create(['name' => 'simpan pembelian']);
        // Permission::create(['name' => 'baca pembelian']);
        // Permission::create(['name' => 'update pembelian']);
        // Permission::create(['name' => 'hapus pembelian']);

        // // CRUD penjualan permissions
        // Permission::create(['name' => 'simpan penjualan']);
        // Permission::create(['name' => 'baca penjualan']);
        // Permission::create(['name' => 'update penjualan']);
        // Permission::create(['name' => 'hapus penjualan']);

        // // CRUD retur permissions
        // Permission::create(['name' => 'simpan retur']);
        // Permission::create(['name' => 'baca retur']);
        // Permission::create(['name' => 'update retur']);
        // Permission::create(['name' => 'hapus retur']);

        // Permission::create(['name' => 'simpan karyawan']);
        // Permission::create(['name' => 'baca karyawan']);
        // Permission::create(['name' => 'update karyawan']);
        // Permission::create(['name' => 'hapus karyawan']);

        // Old attendance permissions - removed in favor of new permission system
        // Permission::create(['name' => 'absen masuk keluar']);
        // Permission::create(['name' => 'baca rekap absensi']);
        // Permission::create(['name' => 'update rekap absensi']);
        // Permission::create(['name' => 'hapus rekap absensi']);

        // CRUD income statement permissions
        // Permission::create(['name' => 'lihat semua laporan']);
        // Permission::create(['name' => 'baca laba rugi']);
        // Permission::create(['name' => 'lihat semua laba rugi']);

        // CRUD kasbon permissions
        // Permission::create(['name' => 'baca kasbon']);
        // Permission::create(['name' => 'simpan kasbon']);
        // Permission::create(['name' => 'update kasbon']);
        // Permission::create(['name' => 'hapus kasbon']);
        // Permission::create(['name' => 'approve kasbon']);

        // CRUD gaji permissions
        // Permission::create(['name' => 'baca gaji']);
        // Permission::create(['name' => 'simpan gaji']);
        // Permission::create(['name' => 'update gaji']);
        // Permission::create(['name' => 'hapus gaji']);
        // Permission::create(['name' => 'approve gaji']);

        // Attendance management permissions (for admin/supervisor)
        // Permission::create(['name' => 'kelola absensi']);
        // Permission::create(['name' => 'baca absensi']);
        // Permission::create(['name' => 'simpan absensi']);
        // Permission::create(['name' => 'update absensi']);
        // Permission::create(['name' => 'hapus absensi']);

        // Activity log permissions
        // Permission::create(['name' => 'baca activity log']);
        // Permission::create(['name' => 'hapus activity log']);

        // Payment settings permissions
        Permission::create(['name' => 'baca pengaturan pembayaran']);
        Permission::create(['name' => 'simpan pengaturan pembayaran']);
        Permission::create(['name' => 'update pengaturan pembayaran']);
    }
}
