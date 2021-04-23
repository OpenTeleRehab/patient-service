<style>
    body {
        font-family: 'Khmer OS', serif;
    }
    h1, h2, h3, h4, h5, h6,
    p {
        margin: 0;
    }
    table {
        border-collapse: collapse;
        margin-bottom: 30px;
        width: 100%;
    }
    table, th, td {
        border: 1px solid black;
    }
    th, td {
        padding: 5px;
        vertical-align: top;
        width: 200px;
    }
    .indent {
        margin-left: 50px;
    }
    .mb-1 {
        margin: 10px;
    }
</style>

<img width="190" src="http://localhost/images/logo-horizontal.svg">

<h1>{{ $translations['menu.user.profile'] ?? 'User Profile' }}</h1>

<table>
    <tbody>
        <tr>
            <th align="left">{{ $translations['common.name'] ?? 'Name' }}</th>
            <td colspan="2">{{ $user->last_name }} {{ $user->first_name }}</td>
        </tr>
        <tr>
            <th align="left">{{ $translations['common.gender'] ?? 'Gender' }}</th>
            <td colspan="2">{{ $translations['gender.' . $user->gender] ?? '' }}</td>
        </tr>
        <tr>
            <th align="left">{{ $translations['date.of.birth'] ?? 'Date of Birth' }}</th>
            <td colspan="2">
                {{ $user->date_of_birth ? \Carbon\Carbon::parse($user->date_of_birth)->format(config('settings.date_format')) : '' }}
            </td>
        </tr>
        <tr>
            <th align="left">{{ $translations['phone.number'] ?? 'Mobile Number' }}</th>
            <td colspan="2">+{{ $user->phone }}</td>
        </tr>
    </tbody>
</table>
