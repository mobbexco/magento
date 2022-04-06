# Mobbex para Magento 1.6 - 1.9

## Instalación
1) Descomprimir la versión descargada
2) Copiar el contenido de "app" y "skin" en el raiz de tu ecommerce
3) Ingresá en el Admin de Magento
4) Dirigite a "Sistema", "Configuración", "Métodos de Pago"
5) Buscá Mobbex y clickealo
6) Configuralo como activo y colocá tu API Key y Access Token obtenidos desde tu consola
7) Guardá la configuración.

## Cómo obtengo mis credenciales ( API Key y Access Token )

[Mobbex Credenciales](https://mobbexco.atlassian.net/servicedesk/customer/kb/view/50266136)

## Credenciales de Prueba:

API Key: ```zJ8LFTBX6Ba8D611e9io13fDZAwj0QmKO1Hn1yIj```
Access Token: ```d31f0721-2f85-44e7-bcc6-15e19d1a53cc```

## Tarjetas de Prueba ( Sólo con credenciales de Prueba )

[Tarjetas de Prueba](https://mobbexco.github.io/#/es/testcards)

## Hooks

Debido a las limitaciones de la plataforma en el manejo de eventos, hemos decidido implementar un método propio para extender las funcionalidades del módulo.

Puntualmente, las diferencias al momento de implementar un observer con estos eventos son las siguientes:
- El método del observer recibe como parámetros los argumentos enviados, en lugar de obtenerlos mediante un parámetro de tipo observer.
- Los valores retornados modifican el resultado obtenido al momento de ejecutar el hook.

A continuación, un ejemplo utilizando el hook `mobbexCheckoutRequest`:
```php
<?php

class Mobbex_Mobbex_Model_Observer
{
    public function mobbexCheckoutRequest($body, $order)
    {
        $body['reference'] = $order->getId();

        return $body;
    }
}
```

Y un ejemplo de como se registra el evento en el archivo `config.xml`. Recuerde que aquí debe escribirse utilizando snake-case:
```xml
            <event_area_selected>
                <observers>
                    <observer_name>
                        <type>type (singleton, model)</type>
                        <class>mobbex/observer</class>
                        <method>nameOfTheMethod</method>
                    </observer_name>
                </observers>
            </event_area_selected>
```

El módulo cuenta con los siguientes hooks actualmente:
<table>
    <thead>
        <tr>
            <th>Nombre</th>
            <th>Utilidad</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>mobbexCheckoutRequest</td>
            <td>Modificar el body que se utiliza para crear el checkout.</td>
        </tr>
        <tr>
            <td>mobbexWebhookReceived</td>
            <td>Guardar datos adicionales al recibir el webhook de Mobbex.</td>
        </tr>
        <tr>
            <td>mobbexProductSettings</td>
            <td>Añadir opciones a la configuración por producto del plugin.</td>
        </tr>
        <tr>
            <td>mobbexCategorySettings</td>
            <td>Añadir opciones a la configuración por categoría del plugin.</td>
        </tr>
    </tbody>
</table>