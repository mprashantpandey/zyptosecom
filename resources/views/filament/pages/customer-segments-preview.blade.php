<div class="space-y-4">
    <p class="text-sm text-gray-600">
        Found <strong>{{ $count }}</strong> matching customers.
    </p>
    
    @if($users->count() > 0)
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="text-left p-2">Name</th>
                        <th class="text-left p-2">Email</th>
                        <th class="text-left p-2">Orders</th>
                        <th class="text-left p-2">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users->take(50) as $user)
                        <tr class="border-b">
                            <td class="p-2">{{ $user->name }}</td>
                            <td class="p-2">{{ $user->email }}</td>
                            <td class="p-2">{{ $user->total_orders }}</td>
                            <td class="p-2">
                                <span class="px-2 py-1 text-xs rounded {{ $user->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $user->is_active ? 'Active' : 'Blocked' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
        @if($users->count() > 50)
            <p class="text-sm text-gray-500 mt-2">
                Showing first 50 of {{ $count }} customers.
            </p>
        @endif
    @else
        <p class="text-sm text-gray-500">No customers match these rules.</p>
    @endif
</div>

