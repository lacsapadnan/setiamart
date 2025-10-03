<div class="modal fade" tabindex="-1" id="kt_modal_1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Tambah data bank</h3>

                <!--begin::Close-->
                <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal" aria-label="Close">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </div>
                <!--end::Close-->
            </div>

            <div class="modal-body">
                <form action="{{ route('bank.store') }}" method="post">
                    @csrf
                    <div class="mb-10">
                        <label class="form-label" for="bank_name">Nama Bank</label>
                        <input name="bank_name" type="text" class="form-control" placeholder="Masukan nama bank" />
                    </div>
                    <div class="mb-10">
                        <label class="form-label" for="account_number">Nomor Rekening</label>
                        <input name="account_number" type="text" class="form-control"
                            placeholder="Masukan nomor rekening" />
                    </div>
                    <div class="mb-10">
                        <label class="form-label" for="account_name">Nama Rekening</label>
                        <input name="account_name" type="text" class="form-control"
                            placeholder="Masukan nama rekening" />
                    </div>
                    <button type="submit" class="btn btn-success">Simpan</button>
                </form>

            </div>
        </div>
    </div>
</div>
