
![Kiku](public/img/brasao.png)

## SISFOLHA

### Configurar o VirtualHost do wamp/xampp
Abra o arquivo **httpd-vhosts.conf** e adicione as seguintes linhas:

```
<VirtualHost *:80>
  ServerName sisfolha.me
  DocumentRoot "${INSTALL_DIR}/www/sisfolha/public"
</VirtualHost>
```
>**Obs.:** Sem a barra deitada pra esquerda em `${INSTALL_DIR}` 

### Configurar arquivo **.htaccess**
Crie o arquivo /public/.htaccess (caso ainda nao o tenha criado) e adicione as seguintes linhas:

```
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

### Configurar o Host do Windows
Inicie o **bloco de notas** em modo **administrador** e abra o arquivo "C:\Windows\System32\drivers\etc\hosts", adicionando a seguinte linha:
```sh
127.0.0.1 sisfolha.me
```
Salve o arquivo e feche o bloco de notas

### Criar o arquivo VariaveisModule.js em resources/js/store/modules/assets com o seguinte conteudo:
```
export default {
    state: {
        baseUrl: window.location.origin ===
        'http://localhost:3000' ?
            (window.location.origin) :
            (window.location.origin === 'http://sisfolha.me' ? 'http://sisfolha.me' : (window.location.origin + '/sisfolha') )
    },
    getters: {
        getBaseUrl(state) {
            return state.baseUrl
        },
    },
}
```


### Como rodar o projeto localmente
Instale os pacotes do composer
```sh
$ composer install
```

Instale os pacotes npm
```sh
$ npm install
```

Rodar o projeto
```sh
$ npm run watch
```
