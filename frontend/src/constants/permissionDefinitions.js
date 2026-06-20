// Shared by RolePermissionView.vue and UserPermissionOverrideDialog.vue so both
// screens always render the exact same permission tree.
export const permissionDefinitions = [
  {
    group: 'Dashboard',
    items: [
      { key: 'dashboard.view', label: 'Lihat Dashboard' }
    ]
  },
  {
    group: 'Booking',
    items: [
      { key: 'booking.view', label: 'Lihat Booking' },
      { key: 'booking.create', label: 'Buat Booking' },
      { key: 'booking.update', label: 'Edit Booking' },
      { key: 'booking.delete', label: 'Hapus / Cancel Booking' },
      { key: 'booking.handle', label: 'Handle Booking (Assign Unit/Driver)' },
      { key: 'booking.supervisor_request', label: 'Request Supervisor' },
      { key: 'booking.payment', label: 'Kelola Pembayaran Booking' }
    ]
  },
  {
    group: 'Cek Fisik',
    items: [
      { key: 'physical_check.view', label: 'Lihat Cek Fisik' },
      { key: 'physical_check.create', label: 'Buat Cek Fisik' },
      { key: 'physical_check.update', label: 'Edit Cek Fisik' }
    ]
  },
  {
    group: 'Keuangan',
    subgroups: [
      {
        name: 'Piutang',
        items: [
          { key: 'finance.receivable', label: 'Lihat Piutang' },
          { key: 'finance.receivable.create', label: 'Buat Invoice' },
          { key: 'finance.receivable.pay', label: 'Bayar Piutang / Invoice' },
          { key: 'finance.receivable.update', label: 'Edit Piutang' }
        ]
      },
      {
        name: 'Rent to Rent',
        items: [
          { key: 'finance.rent_to_rent', label: 'Lihat Rent to Rent' },
          { key: 'finance.rent_to_rent.create', label: 'Buat Tagihan R2R / Bayar' },
          { key: 'finance.rent_to_rent.update', label: 'Edit Rent to Rent' }
        ]
      },
      {
        name: 'Biaya Operasional',
        items: [
          { key: 'finance.operational_cost', label: 'Lihat Biaya Operasional' },
          { key: 'finance.operational_cost.create', label: 'Catat Biaya Operasional' },
          { key: 'finance.operational_cost.update', label: 'Edit Biaya Operasional' }
        ]
      },
      {
        name: 'Mutasi Rekening',
        items: [
          { key: 'finance.account_mutation', label: 'Lihat Mutasi Rekening' },
          { key: 'finance.account_mutation.create', label: 'Buat Mutasi Rekening' }
        ]
      },
      {
        name: 'Laporan',
        items: [
          { key: 'finance.monthly_report', label: 'Laporan Bulanan' },
          { key: 'finance.transaction', label: 'Laporan Transaksi' }
        ]
      },
      {
        name: 'Template Invoice',
        items: [
          { key: 'finance.invoice_terms', label: 'Lihat Template Invoice' },
          { key: 'finance.invoice_terms.create', label: 'Buat Template Invoice' },
          { key: 'finance.invoice_terms.update', label: 'Edit Template Invoice' },
          { key: 'finance.invoice_terms.delete', label: 'Hapus Template Invoice' }
        ]
      }
    ]
  },
  {
    group: 'Kendaraan',
    subgroups: [
      {
        name: 'Pemilik Rental',
        items: [
          { key: 'vehicle.rental_owner', label: 'Lihat Pemilik Rental' },
          { key: 'vehicle.rental_owner.create', label: 'Tambah Pemilik Rental' },
          { key: 'vehicle.rental_owner.update', label: 'Edit Pemilik Rental' },
          { key: 'vehicle.rental_owner.delete', label: 'Hapus Pemilik Rental' }
        ]
      },
      {
        name: 'Unit',
        items: [
          { key: 'vehicle.unit', label: 'Lihat Unit Kendaraan' },
          { key: 'vehicle.unit.create', label: 'Tambah Unit Kendaraan' },
          { key: 'vehicle.unit.update', label: 'Edit Unit Kendaraan' },
          { key: 'vehicle.unit.delete', label: 'Hapus Unit Kendaraan' }
        ]
      },
      {
        name: 'Driver',
        items: [
          { key: 'vehicle.driver', label: 'Lihat Driver' },
          { key: 'vehicle.driver.create', label: 'Tambah Driver' },
          { key: 'vehicle.driver.update', label: 'Edit Driver' },
          { key: 'vehicle.driver.delete', label: 'Hapus Driver' },
          { key: 'driver.operational', label: 'Akses Operasional Driver (Mobile)' }
        ]
      }
    ]
  },
  {
    group: 'Pelanggan & Member',
    subgroups: [
      {
        name: 'Pelanggan',
        items: [
          { key: 'customer.view', label: 'Lihat Pelanggan' },
          { key: 'customer.create', label: 'Tambah Pelanggan' },
          { key: 'customer.update', label: 'Edit Pelanggan' },
          { key: 'customer.delete', label: 'Hapus Pelanggan' }
        ]
      },
      {
        name: 'Member',
        items: [
          { key: 'member.view', label: 'Lihat Member' },
          { key: 'member.create', label: 'Tambah Member' },
          { key: 'member.update', label: 'Edit Member' },
          { key: 'member.delete', label: 'Hapus Member' }
        ]
      }
    ]
  },
  {
    group: 'Data Master',
    subgroups: [
      {
        name: 'Manajemen User',
        items: [
          { key: 'master.user', label: 'Lihat Manajemen User' },
          { key: 'master.user.create', label: 'Tambah User' },
          { key: 'master.user.update', label: 'Edit User' },
          { key: 'master.user.delete', label: 'Hapus User' }
        ]
      },
      {
        name: 'Akun Pembayaran',
        items: [
          { key: 'master.payment_account', label: 'Lihat Akun Pembayaran' },
          { key: 'master.payment_account.create', label: 'Tambah Akun Pembayaran' },
          { key: 'master.payment_account.update', label: 'Edit Akun Pembayaran' },
          { key: 'master.payment_account.delete', label: 'Hapus Akun Pembayaran' }
        ]
      },
      {
        name: 'Kota',
        items: [
          { key: 'master.city', label: 'Lihat Kota' },
          { key: 'master.city.create', label: 'Tambah Kota' },
          { key: 'master.city.update', label: 'Edit Kota' },
          { key: 'master.city.delete', label: 'Hapus Kota' }
        ]
      },
      {
        name: 'Tipe Biaya',
        items: [
          { key: 'master.cost_type', label: 'Lihat Tipe Biaya' },
          { key: 'master.cost_type.create', label: 'Tambah Tipe Biaya' },
          { key: 'master.cost_type.update', label: 'Edit Tipe Biaya' },
          { key: 'master.cost_type.delete', label: 'Hapus Tipe Biaya' }
        ]
      },
      {
        name: 'Paket Harga',
        items: [
          { key: 'master.pricing_package', label: 'Lihat Paket Harga' },
          { key: 'master.pricing_package.create', label: 'Tambah Paket Harga' },
          { key: 'master.pricing_package.update', label: 'Edit Paket Harga' },
          { key: 'master.pricing_package.delete', label: 'Hapus Paket Harga' }
        ]
      },
      {
        name: 'Kategori Keuangan',
        items: [
          { key: 'master.finance_category', label: 'Lihat Kategori Keuangan' },
          { key: 'master.finance_category.create', label: 'Tambah Kategori Keuangan' },
          { key: 'master.finance_category.update', label: 'Edit Kategori Keuangan' },
          { key: 'master.finance_category.delete', label: 'Hapus Kategori Keuangan' }
        ]
      },
      {
        name: 'Cabang',
        items: [
          { key: 'master.branch', label: 'Lihat Cabang' },
          { key: 'master.branch.create', label: 'Tambah Cabang' },
          { key: 'master.branch.update', label: 'Edit Cabang' },
          { key: 'master.branch.delete', label: 'Hapus Cabang' }
        ]
      },
      {
        name: 'Tenant & Role',
        items: [
          { key: 'master.tenant', label: 'Profil Tenant' },
          { key: 'master.role_management', label: 'Manajemen Role Permission' }
        ]
      }
    ]
  }
]
