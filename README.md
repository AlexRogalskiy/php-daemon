
# PHP-daemon
The PHP Daemon showcase

1. Install dependencies:

```
[Install Vagrant](https://www.vagrantup.com/)
```

2. Run application enviroment [virtual box]:

```
vagrant up
```

3. Connect via ssh to the running virtual box:

```
vagrant ssh
```

4. Run daemon service:

```
cd /vagrant
php composer.phar install (local) or composer install {global}
php src/index.php
```

5. Check daemon status:

```
ps -aux | grep php
```

6. Check mail box:

```
cat /var/mail/vagrant
```

7. Terminate daemon service:

```
kill -SIGTERM <pid>
```

8. Check daemon logs:

```
cd /logs
```