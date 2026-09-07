<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RespondsJsonOrRedirect;
use App\Http\Controllers\Controller;
use App\Mail\CustomerStatementMail;
use App\Models\Company;
use App\Models\Customer;
use App\Services\Customers\GenerateCustomerStatement;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * Customer statement — the dated event log + running balance you hand
 * to a credit customer. Three surfaces:
 *
 *   GET  /admin/customers/{customer}/statement
 *       Admin shell with date-range filter + view-only event table.
 *
 *   GET  /admin/customers/{customer}/statement/print
 *       Standalone paper-style layout, fires `window.print()` on load.
 *       Same Blade is used by the email mailable so the wire stays
 *       single-source.
 *
 *   POST /admin/customers/{customer}/statement/email
 *       Sends the statement to the customer's email via the configured
 *       SMTP (or whatever mailer the company has set).
 *
 * PDF generation is deferred — the print layout renders cleanly via
 * Ctrl+P → Save as PDF until a real mPDF integration lands.
 */
class CustomerStatementController extends Controller
{
    use RespondsJsonOrRedirect;

    public function __construct(private GenerateCustomerStatement $generator) {}

    public function show(Request $request, Customer $customer): View
    {
        $this->authorize('view', $customer);

        [$from, $to] = $this->parseRange($request);
        $statement   = ($this->generator)($customer, $from, $to);

        return view('admin.customers.statement.index', $statement + [
            'company' => Company::current() ?? new Company(),
        ]);
    }

    public function print(Request $request, Customer $customer): View
    {
        $this->authorize('view', $customer);

        [$from, $to] = $this->parseRange($request);
        $statement   = ($this->generator)($customer, $from, $to);

        return view('admin.customers.statement.print', $statement + [
            'company'    => Company::current() ?? new Company(),
            'autoPrint'  => true,
        ]);
    }

    public function email(Request $request, Customer $customer): JsonResponse|RedirectResponse
    {
        $this->authorize('view', $customer);

        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to'   => ['nullable', 'date'],
            'to_email' => ['nullable', 'email'],
        ]);

        $recipient = ($data['to_email'] ?? null) ?: $customer->email;
        if (! $recipient) {
            return $this->jsonOrError(
                $request,
                __('customer_statement.errors.no_email'),
                route('admin.customers.statement.show', $customer),
            );
        }

        [$from, $to] = $this->parseRange($request);
        $statement   = ($this->generator)($customer, $from, $to);

        try {
            Mail::to($recipient)->send(new CustomerStatementMail(
                statement: $statement + ['company' => Company::current() ?? new Company()],
            ));
        } catch (\Throwable $e) {
            return $this->jsonOrError(
                $request,
                __('customer_statement.errors.send_failed', ['error' => $e->getMessage()]),
                route('admin.customers.statement.show', $customer),
            );
        }

        return $this->jsonOrRedirect(
            $request,
            __('customer_statement.flash.sent', ['email' => $recipient]),
            route('admin.customers.statement.show', $customer),
        );
    }

    /**
     * Parse `from` / `to` query params with sane defaults. Range defaults
     * to the last 3 months when nothing's supplied so the screen lands
     * on the most useful window.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function parseRange(Request $request): array
    {
        $to = $request->query('to');
        $to = $to ? CarbonImmutable::parse((string) $to) : CarbonImmutable::today();

        $from = $request->query('from');
        $from = $from ? CarbonImmutable::parse((string) $from) : $to->subMonths(3);

        return [$from->startOfDay(), $to->endOfDay()];
    }
}
