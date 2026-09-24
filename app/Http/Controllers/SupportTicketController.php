<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One helpdesk controller for both buyers and vendors — a ticket only ever belongs to a User, and
 * the frontend page picks StorefrontLayout or VendorLayout based on that user's role (see
 * resources/js/Pages/Support/*.jsx). Admin triages/replies from Filament\Resources\SupportTicketResource.
 */
class SupportTicketController extends Controller
{
    public function index(Request $request): Response
    {
        $tickets = $request->user()->supportTickets()
            ->latest('updated_at')
            ->paginate(15);

        return Inertia::render('Support/Index', [
            'tickets' => $tickets,
            'vendor' => $request->user()->vendor,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Support/Create', [
            'vendor' => $request->user()->vendor,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:order,payment,product,account,other'],
            'priority' => ['required', 'in:low,normal,high'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $ticket = $request->user()->supportTickets()->create([
            'subject' => $validated['subject'],
            'category' => $validated['category'],
            'priority' => $validated['priority'],
            'status' => 'open',
        ]);

        $ticket->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        return redirect()->route('support.show', $ticket->id)->with('success', 'Support ticket submitted — our team will respond soon.');
    }

    public function show(Request $request, SupportTicket $ticket): Response
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);

        $ticket->load('messages.user');

        return Inertia::render('Support/Show', [
            'ticket' => $ticket,
            'vendor' => $request->user()->vendor,
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $ticket->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        // A user replying to their own resolved/closed ticket means the issue isn't actually done —
        // reopen it so it surfaces in the admin's "open" queue again instead of staying hidden.
        if (in_array($ticket->status, ['resolved', 'closed'], true)) {
            $ticket->update(['status' => 'open']);
        } else {
            $ticket->touch();
        }

        return back()->with('success', 'Reply sent.');
    }
}
