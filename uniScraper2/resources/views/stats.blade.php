<!DOCTYPE html>
<html>
<head>
    <title>University Course Statistics</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #f5f5f5;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
    </style>
</head>
<body>
    <h1>University Course Statistics</h1>
    <table>
        <thead>
            <tr>
                <th>University</th>
                <th>City</th>
                <th>Number of Courses</th>
            </tr>
        </thead>
        <tbody>
            @foreach($universities as $university)
            <tr>
                <td>{{ $university->name }}</td>
                <td>{{ $university->city }}</td>
                <td>{{ $university->courses_count }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html> 