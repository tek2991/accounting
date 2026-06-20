@extends('accounting::pdf.reports.layout')

@section('content')
    <table>
        <thead>
            <tr>
                <th>Account Code</th>
                <th>Account Name</th>
                <th class="text-right">Balance</th>
            </tr>
        </thead>
        <tbody>
            <tr class="group-header">
                <td colspan="3">ASSETS</td>
            </tr>
            @foreach($data['assets'] as $class => $items)
                <tr>
                    <td colspan="3" style="font-weight: bold; font-style: italic; padding-left: 15px;">{{ $class }}</td>
                </tr>
                @foreach($items as $row)
                    <tr>
                        <td style="padding-left: 30px;">{{ $row['account']->code }}</td>
                        <td>{{ $row['account']->name }}</td>
                        <td class="text-right">{{ $row['balance']->format() }}</td>
                    </tr>
                @endforeach
            @endforeach
            <tr class="summary-row">
                <td colspan="2" class="text-right">Total Assets</td>
                <td class="text-right">{{ $data['totalAssets']->format() }}</td>
            </tr>

            <tr class="group-header">
                <td colspan="3"><br>LIABILITIES</td>
            </tr>
            @foreach($data['liabilities'] as $class => $items)
                <tr>
                    <td colspan="3" style="font-weight: bold; font-style: italic; padding-left: 15px;">{{ $class }}</td>
                </tr>
                @foreach($items as $row)
                    <tr>
                        <td style="padding-left: 30px;">{{ $row['account']->code }}</td>
                        <td>{{ $row['account']->name }}</td>
                        <td class="text-right">{{ $row['balance']->format() }}</td>
                    </tr>
                @endforeach
            @endforeach
            <tr class="summary-row">
                <td colspan="2" class="text-right">Total Liabilities</td>
                <td class="text-right">{{ $data['totalLiabilities']->format() }}</td>
            </tr>

            <tr class="group-header">
                <td colspan="3"><br>EQUITY</td>
            </tr>
            @foreach($data['equity'] as $class => $items)
                <tr>
                    <td colspan="3" style="font-weight: bold; font-style: italic; padding-left: 15px;">{{ $class }}</td>
                </tr>
                @foreach($items as $row)
                    <tr>
                        <td style="padding-left: 30px;">{{ $row['account']->code }}</td>
                        <td>{{ $row['account']->name }}</td>
                        <td class="text-right">{{ $row['balance']->format() }}</td>
                    </tr>
                @endforeach
            @endforeach
            <tr>
                <td style="padding-left: 30px;"></td>
                <td>Retained Earnings / Net Income</td>
                <td class="text-right">{{ $data['netIncome']->format() }}</td>
            </tr>
            <tr class="summary-row">
                <td colspan="2" class="text-right">Total Equity</td>
                <td class="text-right">{{ (new \Tek2991\Accounting\ValueObjects\Money($data['totalEquity']->getAmount() + $data['netIncome']->getAmount(), $data['totalEquity']->getCurrencyCode()))->format() }}</td>
            </tr>

            <tr class="summary-row" style="border-top: 3px double #333;">
                <td colspan="2" class="text-right">Total Liabilities & Equity</td>
                <td class="text-right">{{ $data['totalLiabilitiesAndEquity']->format() }}</td>
            </tr>
        </tbody>
    </table>
@endsection
