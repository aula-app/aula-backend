{{-- TODO: i18n & l10n? In this special case, maybe just append everything in english below? --}}
{{-- TODO: see TODO in config/aula.php re: templates --}}

<p>Hallo {{ $name }},</p>

<p>herzlich willkommen auf der Beteiligungsplattform aula! Wir freuen uns, dass du dabei bist. Bei Aula kannst du aktiv an Entscheidungen mitwirken und deine Ideen einbringen.</p>

<p>Benutzername: {{ $username }}</p>

<p>
Um loszulegen, kannst du hier dein Passwort erstellen:
<a href="{{ $secretLink }}">{{ $secretLink }}</a>
</p>

<ul>
    <li>Link (siehe oben) öffnen</li>
    <li>Instanzcode eingeben (den bekommst du von deiner Schule/Einrichtung)</li>
    <li>Link (siehe oben) wieder in die Adresszeile eingeben</li>
    <li>Passwort festlegen</li>
    <li>einloggen!</li>
</ul>

<p>Nach der Anmeldung kannst du:</p>

<ul>
    <li>Neue Ideen und Verbesserungsvorschläge posten</li>
    <li>An Diskussionen und Abstimmungen teilnehmen</li>
</ul>

<p>
Wenn du mehr über aula und das Konzept erfahren möchtest, schau doch mal auf unserer
E-Learning-Plattform (<a href="https://learning.aula.de">learning.aula.de</a>) oder unserer
Website (<a href="https://www.aula.de">aula.de</a>) vorbei.
</p>

<p>
Alle Infos zu den Funktionen und Möglichkeiten auf unserer Plattform findest du in unserem Docs (<a href="https://docs.aula.de">docs.aula.de</a>).<br>
Wenn du Hilfe brauchst oder Fragen hast, ist unser Support-Team immer für dich da. Schick uns einfach eine E-Mail an <a href="mailto:support@aula.de">support@aula.de</a>.
</p>

<p>Nochmals ein herzliches Willkommen und viel Spaß beim Mitgestalten!</p>

<p>
Viele Grüße,<br/>
Dein aula-Team
</p>
