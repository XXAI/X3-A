# Plataforma Base Offline

Esta plataforma esta creada bajo un esquema offline con un proceso de sincronización a un servidor central.

## Tecnología

- Laravel 5.2
- JWT

## Configuración para parches

Los parches de git se ejecutan con *sudo*. Para que apache tenga permiso de ejecutarlos hay que modificar al archivo */etc/sudoers.tmp*, abrimos la edición con el siguiente comando:

`visudo`

Este comando nos abrió el archivo *sudoers*, agregar hasta el final la siguiente línea:

`apache ALL=(ALL) NOPASSWD: /usr/bin/git pull *, /usr/bin/git am *, /usr/bin/git apply *`

Guardar y salir (:wq). En esta caso "apache" es el nombre de usuario con que apache ejecuta los archivos.
