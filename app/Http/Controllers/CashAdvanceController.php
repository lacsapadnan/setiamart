<?php

namespace App\Http\Controllers;

use App\Http\Requests\CashAdvanceRejectRequest;
use App\Http\Requests\CashAdvanceStoreRequest;
use App\Http\Requests\CashAdvanceUpdateRequest;
use App\Models\CashAdvance;
use App\Models\CashAdvancePayment;
use App\Models\Employee;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashAdvanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:baca kasbon')->only(['index', 'data', 'show']);
        $this->middleware('can:simpan kasbon')->only(['create', 'store']);
        $this->middleware('can:update kasbon')->only(['edit', 'update']);
        $this->middleware('can:hapus kasbon')->only(['destroy']);
        $this->middleware('can:approve kasbon')->only(['approve', 'reject']);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $warehouses = Warehouse::orderBy('name')->get();
        $employees = Employee::orderBy('name')->get();

        return view('pages.cash-advance.index', compact('warehouses', 'employees'));
    }

    /**
     * Get data for DataTables
     */
    public function data(Request $request)
    {
        $isMaster = auth()->user()->hasRole('master');
        $query = CashAdvance::with(['employee', 'warehouse', 'approvedBy'])
            ->orderBy('created_at', 'desc');

        // Role-based filtering
        if (! $isMaster) {
            $query->where('warehouse_id', auth()->user()->warehouse_id);
        }

        // Apply filters
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereBetween('advance_date', [$request->from_date, $request->to_date]);
        }

        $cashAdvances = $query->get();

        return response()->json([
            'data' => $cashAdvances,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $isMaster = auth()->user()->hasRole('master');
        $warehouses = Warehouse::orderBy('name')->get();

        if ($isMaster) {
            $employees = Employee::orderBy('name')->get();
        } else {
            $employees = Employee::where('warehouse_id', auth()->user()->warehouse_id)
                ->orderBy('name')->get();
        }

        return view('pages.cash-advance.create', compact('warehouses', 'employees'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CashAdvanceStoreRequest $request)
    {
        try {
            DB::beginTransaction();

            $cashAdvance = CashAdvance::create([
                'employee_id' => $request->employee_id,
                'warehouse_id' => $request->warehouse_id,
                'amount' => $request->amount,
                'advance_date' => $request->advance_date,
                'type' => $request->type,
                'installment_count' => $request->type === 'installment' ? $request->installment_count : null,
                'description' => $request->description,
            ]);

            // Create installment payment records if installment type
            if ($request->type === 'installment') {
                $this->createInstallmentPayments($cashAdvance);
            }

            DB::commit();

            return redirect()->route('kasbon.index')->with('success', 'Kasbon berhasil dibuat');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);

            return redirect()->back()->withErrors(['error' => 'Terjadi kesalahan saat membuat kasbon.'])->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $cashAdvance = CashAdvance::with(['employee', 'warehouse', 'approvedBy', 'payments.processedBy'])->findOrFail($id);

        return view('pages.cash-advance.show', compact('cashAdvance'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $cashAdvance = CashAdvance::with(['employee', 'warehouse', 'approvedBy', 'payments.processedBy'])->findOrFail($id);
        // Only allow editing if status is pending
        if ($cashAdvance->status !== 'pending') {
            return redirect()->route('kasbon.index')->with('error', 'Kasbon yang sudah diproses tidak dapat diedit');
        }

        $isMaster = auth()->user()->hasRole('master');
        $warehouses = Warehouse::orderBy('name')->get();

        if ($isMaster) {
            $employees = Employee::orderBy('name')->get();
        } else {
            $employees = Employee::where('warehouse_id', auth()->user()->warehouse_id)->orderBy('name')->get();
        }

        return view('pages.cash-advance.edit', compact('cashAdvance', 'warehouses', 'employees'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CashAdvanceUpdateRequest $request, string $id)
    {
        $cashAdvance = CashAdvance::findOrFail($id);
        // Only allow updating if status is pending
        if ($cashAdvance->status !== 'pending') {
            return redirect()->route('kasbon.index')->with('error', 'Kasbon yang sudah diproses tidak dapat diedit');
        }

        try {
            DB::beginTransaction();

            if ($cashAdvance->type === 'installment' || $request->type === 'installment') {
                $cashAdvance->payments()->delete();
            }

            $cashAdvance->update([
                'employee_id' => $request->employee_id,
                'warehouse_id' => $request->warehouse_id,
                'amount' => $request->amount,
                'advance_date' => $request->advance_date,
                'type' => $request->type,
                'installment_count' => $request->type === 'installment' ? $request->installment_count : null,
                'description' => $request->description,
            ]);

            // Create installment payments if the new type is installment
            if ($request->type === 'installment') {
                $this->createInstallmentPayments($cashAdvance);
            }

            DB::commit();

            return redirect()->route('kasbon.index')->with('success', 'Kasbon berhasil diperbarui');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);

            return redirect()->back()->withErrors(['error' => 'Terjadi kesalahan saat memperbarui kasbon.'])->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $cashAdvance = CashAdvance::findOrFail($id);

        // Ensure status is properly cast to string for comparison
        $status = (string) $cashAdvance->status;

        // Only allow deletion if status is pending or rejected
        if ($status !== 'pending' && $status !== 'rejected') {
            return response()->json(['success' => false, 'message' => 'Hanya kasbon dengan status pending atau ditolak yang dapat dihapus']);
        }

        try {
            $cashAdvance->delete();
            // Set session flash message for SweetAlert
            session()->flash('success', 'Kasbon berhasil dihapus');

            return response()->json(['success' => true, 'message' => 'Kasbon berhasil dihapus']);
        } catch (\Exception $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan saat menghapus kasbon.']);
        }
    }

    /**
     * Approve cash advance
     */
    public function approve(Request $request, CashAdvance $cashAdvance)
    {
        if ($cashAdvance->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Kasbon ini sudah diproses']);
        }

        try {
            $cashAdvance->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            // Set session flash message for SweetAlert
            session()->flash('success', 'Kasbon berhasil disetujui');

            return response()->json(['success' => true, 'message' => 'Kasbon berhasil disetujui']);
        } catch (\Exception $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan saat menyetujui kasbon.']);
        }
    }

    /**
     * Reject cash advance
     */
    public function reject(CashAdvanceRejectRequest $request, CashAdvance $cashAdvance)
    {
        if ($cashAdvance->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Kasbon ini sudah diproses']);
        }

        try {
            $cashAdvance->update([
                'status' => 'rejected',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'rejection_reason' => $request->rejection_reason,
            ]);

            // Set session flash message for SweetAlert
            session()->flash('success', 'Kasbon berhasil ditolak');

            return response()->json(['success' => true, 'message' => 'Kasbon berhasil ditolak']);
        } catch (\Exception $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan saat menolak kasbon.']);
        }
    }

    /**
     * Create installment payment records
     */
    private function createInstallmentPayments(CashAdvance $cashAdvance)
    {
        if ($cashAdvance->type !== 'installment' || ! $cashAdvance->installment_count) {
            return;
        }

        $installmentAmount = $cashAdvance->amount / $cashAdvance->installment_count;
        $dueDate = Carbon::parse($cashAdvance->advance_date)->addMonth();

        for ($i = 1; $i <= $cashAdvance->installment_count; $i++) {
            CashAdvancePayment::create([
                'cash_advance_id' => $cashAdvance->id,
                'processed_by' => auth()->id(),
                'installment_number' => $i,
                'amount' => $installmentAmount,
                'due_date' => $dueDate->copy(),
                'status' => 'pending',
            ]);

            $dueDate->addMonth();
        }
    }
}
