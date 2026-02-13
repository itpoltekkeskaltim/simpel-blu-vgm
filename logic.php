namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Booking;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function store(Request $request)
    {
        // 1. Validasi Input
        $request->validate([
            'asset_id' => 'required',
            'tgl_mulai' => 'required|date|after:today',
            'tgl_selesai' => 'required|date|after:tgl_mulai',
        ]);

        // 2. Cek Ketersediaan (Cegah Bentrok Jadwal)
        $isBooked = Booking::where('asset_id', $request->asset_id)
            ->where(function ($query) use ($request) {
                $query->whereBetween('tgl_mulai', [$request->tgl_mulai, $request->tgl_selesai])
                      ->orWhereBetween('tgl_selesai', [$request->tgl_mulai, $request->tgl_selesai]);
            })
            ->whereIn('status_booking', ['Pending', 'Disetujui'])
            ->exists();

        if ($isBooked) {
            return back()->with('error', 'Aset sudah dipesan pada tanggal tersebut.');
        }

        // 3. Hitung Total Biaya & Simpan
        $asset = Asset::find($request->asset_id);
        $durasi = date_diff(date_create($request->tgl_mulai), date_create($request->tgl_selesai))->days + 1;
        
        Booking::create([
            'user_id' => auth()->id(),
            'asset_id' => $request->asset_id,
            'tgl_mulai' => $request->tgl_mulai,
            'tgl_selesai' => $request->tgl_selesai,
            'total_biaya' => $durasi * $asset->harga_per_hari,
            'status_booking' => 'Pending'
        ]);

        return redirect()->route('dashboard')->with('success', 'Pesanan berhasil dibuat, silakan lakukan pembayaran.');
    }
}
