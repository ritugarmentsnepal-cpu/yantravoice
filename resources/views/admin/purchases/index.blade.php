@extends('layouts.admin')
@section('title', 'Credit Purchases')

@section('content')
<div class="space-y-5">
    {{-- Status Filter Tabs --}}
    <div class="flex gap-2">
        @foreach(['pending' => '⏳ Pending', 'approved' => '✅ Approved', 'rejected' => '❌ Rejected', 'all' => '📋 All'] as $key => $label)
            <a href="?status={{ $key }}" 
               class="px-4 py-2 rounded-full text-xs font-bold transition-colors {{ $status === $key ? 'bg-[#1B2438] text-white' : 'bg-white text-[#64748B] hover:bg-[#F0F4F8] border border-[#E8EDF2]' }}">
                {{ $label }}
                @if($key === 'pending') <span class="ml-1 bg-[#F97316] text-white px-1.5 py-0.5 rounded-full text-[9px]">{{ $pendingCount }}</span> @endif
            </a>
        @endforeach
    </div>

    {{-- Purchase Requests --}}
    @forelse($purchases as $p)
        <div class="glass-panel p-5">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <h3 class="text-sm font-bold text-[#1B2438]">{{ $p->user->name }}</h3>
                    <p class="text-[10px] text-[#94A3B8]">{{ $p->user->email }} · {{ $p->created_at->diffForHumans() }}</p>
                </div>
                <div class="text-right">
                    @if($p->status === 'pending')
                        <span class="inline-block px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-700 text-[9px] font-bold">Pending</span>
                    @elseif($p->status === 'approved')
                        <span class="inline-block px-2 py-0.5 rounded-full bg-green-100 text-green-700 text-[9px] font-bold">Approved</span>
                    @else
                        <span class="inline-block px-2 py-0.5 rounded-full bg-red-100 text-red-600 text-[9px] font-bold">Rejected</span>
                    @endif
                </div>
            </div>

            {{-- Package Info --}}
            <div class="grid grid-cols-3 gap-2 mb-3">
                <div class="bg-[#F0F4F8] rounded-xl p-3 text-center">
                    <p class="text-lg font-extrabold text-[#1B2438]">₨{{ number_format($p->package_amount) }}</p>
                    <p class="text-[9px] text-[#94A3B8] font-bold uppercase">Package</p>
                </div>
                <div class="bg-[#F0F4F8] rounded-xl p-3 text-center">
                    <p class="text-lg font-extrabold text-green-600">+{{ $p->bonus_amount }}</p>
                    <p class="text-[9px] text-[#94A3B8] font-bold uppercase">Bonus</p>
                </div>
                <div class="bg-[#F0F4F8] rounded-xl p-3 text-center">
                    <p class="text-lg font-extrabold text-[#F97316]">{{ number_format($p->total_credits) }}</p>
                    <p class="text-[9px] text-[#94A3B8] font-bold uppercase">Total</p>
                </div>
            </div>

            {{-- Screenshot --}}
            @if($p->payment_screenshot)
                <div class="mb-3">
                    <p class="text-[10px] text-[#94A3B8] font-bold mb-1 uppercase">Payment Screenshot</p>
                    <img src="{{ asset('storage/' . $p->payment_screenshot) }}" alt="Payment" 
                         class="w-full max-h-64 object-contain rounded-xl border border-[#E8EDF2] cursor-pointer"
                         onclick="window.open(this.src, '_blank')">
                </div>
            @endif

            {{-- Admin Note --}}
            @if($p->admin_note)
                <div class="p-2 rounded-lg bg-red-50 border border-red-100 mb-3">
                    <p class="text-xs text-red-600"><strong>Note:</strong> {{ $p->admin_note }}</p>
                </div>
            @endif

            {{-- Processed info --}}
            @if($p->processor)
                <p class="text-[10px] text-[#94A3B8] mb-3">Processed by {{ $p->processor->name }} · {{ $p->processed_at->diffForHumans() }}</p>
            @endif

            {{-- Actions --}}
            @if($p->status === 'pending')
                <div class="flex gap-2">
                    <form method="POST" action="{{ route('admin.purchases.approve', $p->id) }}" class="flex-1">
                        @csrf
                        <button type="submit" class="w-full py-2.5 bg-green-500 hover:bg-green-600 text-white font-bold text-xs rounded-full transition-colors"
                                onclick="return confirm('Approve and grant {{ number_format($p->total_credits) }} credits to {{ $p->user->name }}?')">
                            ✅ Approve & Grant Credits
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.purchases.reject', $p->id) }}" class="flex-1">
                        @csrf
                        <input type="hidden" name="note" value="Payment could not be verified.">
                        <button type="submit" class="w-full py-2.5 bg-red-500 hover:bg-red-600 text-white font-bold text-xs rounded-full transition-colors"
                                onclick="return confirm('Reject this purchase request?')">
                            ❌ Reject
                        </button>
                    </form>
                </div>
            @endif
        </div>
    @empty
        <div class="glass-panel p-8 text-center">
            <div class="text-3xl mb-2">📭</div>
            <p class="text-sm text-[#94A3B8]">No {{ $status === 'all' ? '' : $status }} purchase requests.</p>
        </div>
    @endforelse

    {{ $purchases->appends(['status' => $status])->links() }}
</div>
@endsection
