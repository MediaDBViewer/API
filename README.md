# API
In diesen Projekt befindet sich die API für die MediaDB. Wird benötigt für die Android-App "MediaDBViewer"

## Installation der API auf einen Webserver
* Der Inhalt des Verzeichnis muss direkt in den Webroot eines Apache2 Server bzw. vhost gelegt werden.
* Die Key.sqlite und Rights.json müssen so abgelegt werden, dass kein Zugriff aus dem Internet möglich ist.
* Die Pfade für Key.sqlite und Rights.json sind in der api.class.php anzupassen.

## Voraussetzung
* Apache2 Webserver mit installierten php5
