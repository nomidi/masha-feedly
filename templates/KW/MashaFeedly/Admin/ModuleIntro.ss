<section class="masha-module-intro" aria-labelledby="$HeadingID.ATT">
    <div class="masha-module-intro__copy">
        <span class="masha-module-intro__eyebrow">$ModuleName.XML</span>
        <h1 id="$HeadingID.ATT">$Title.XML</h1>
        <p>$Description.XML</p>
        <div class="masha-module-intro__help">
            <% if $HelpHTML %>$HelpHTML.RAW<% else %><button type="button" class="masha-module-intro__help-unavailable" disabled>$HelpUnavailable.XML</button><% end_if %>
        </div>
    </div>
    <span class="masha-module-intro__mark" aria-hidden="true"><img src="$IconURL.ATT" alt="" width="104" height="104"></span>
</section>
