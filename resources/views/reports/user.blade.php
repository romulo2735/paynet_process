<!DOCTYPE html>
<html>
<head>
    <title>Relatório de Usuário</title>
</head>
<body>
<h1>Relatório do Usuário</h1>
    <p><strong>CPF:</strong> {{ $data['cpf'] }}</p>
    <p><strong>Email:</strong> {{ $data['email'] }}</p>
    <p><strong>CEP:</strong> {{ $data['cep'] }}</p>
    <p><strong>Endereço:</strong> {{ json_encode($data['address']) }}</p>
    <p><strong>Nacionalidade:</strong> {{ json_encode($data['nationality']) }}</p>
    <p><strong>Status do CPF:</strong> {{ $data['cpf_status'] }}</p>
    <p><strong>Risco:</strong> {{ $data['risk'] }}</p>
</body>
</html>
