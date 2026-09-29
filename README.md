ePay Links Payments
-------------------

**ePay Links Payments** es un plugin para Wordpress que permite generar links de pago y manejar pagos parciales utilizando la pasarela ePay. Además, ofrece funcionalidades para ver el estado de las transacciones.

## Características
- Generación de links de pago para órdenes específicas.
- Soporte para pagos parciales con múltiples tarjetas.
- Formulario con búsqueda de órdenes abiertas para generar links de pago.
- Tabla para visualizar el estado de las transacciones, incluyendo el link de pago, número de orden, estado, fechas de creación y aprobación.
- Actualización automática del estado de las transacciones y órdenes basadas en pagos recibidos.

## Requisitos
- WordPress 5.0 o superior.
- WooCommerce 5.0 o superior.
- PHP 7.2 o superior.

## Instalación
1 Sube el Plugin:
  - En el panel de administración de WordPress, ve a Plugins > Añadir nuevo > Subir plugin.
  - Selecciona el archivo .zip y haz clic en Instalar ahora.
2 Activa el Plugin:
  - Una vez instalado, haz clic en Activar para activar el plugin.
3 Añadir una pagina nueva llamada `epay`. Debe asegurarse que el slug de la pagina sea `epay`.
4 Añadir en la pagina nueva el siguiente short_code `[epay_payment_form]`.

## Uso
### Generar Link de Pago
1 Ve a `WooCommerce > ePay Links`.
2 Ingresar el nombre del producto el formulario.
3 Introduce el monto a cubrir.
4 Haz clic en `Generar Link de Pago`.
  - Se generará un link de pago único, que se mostrará en la tabla de transacciones.
### Ver Estado de Transacciones
1 Ve a `WooCommerce > ePay Links`.
2 La tabla de transacciones mostrará todos los links de pago generados, junto con el estado de cada transacción.
3 Los filtros permiten buscar por nombre del producto.

## Actualización del Estado de la Transacción
El plugin actualizará automáticamente el estado de la transacción cuando se complete el pago total a través del link generado.
