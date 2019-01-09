[![Build Status](https://travis-ci.org/Einrichtungshaus-Ostermann/ost-dynamic-categories.svg?branch=master)](https://travis-ci.org/Einrichtungshaus-Ostermann/ost-dynamic-categories)
# Dynamic Categories
Das Plugin fügt einen Konsolen Befehl hinzu, womit die Kategorien, welche einen ProductStream haben die Artikel von 
diesem zugeordnet bekommen.

Desweiteren werden zwei neue Filter hinzugefügt, "Artikel Name Filter" und der "Artikel Beschreibung Filter". Diese 
Filter nehmen die Suchbegriffe Semikolon getrennt für eine ODER-Verbindung und mit leerzeichen getrennt für eine 
UND-Verbindung.

Beispiel: "Lavalampe;Lampe e17" Es würden alle Artikel mit "Lavalampe" ODER mit "Lampe" UND "e17" gefunden werden.

"Keine Kategorie Filter" ist dazu da um diesen Stream nicht beim ersten lauf zu nutzen, sondern erst wenn alle
anderen Streams durchgelaufen sind. Damit kann man dann alle Artikel greifen die bei den normalen Streams keine 
Kategorie bekommen haben in eine Zubehör Kategorie eintragen.

Die Ausführung erfolgt über den "ost-dynamic-category:rebuild-categories" Befehl, der auch eine Subshop ID
als zusätzlicher, optionaler Parameter akzeptiert.