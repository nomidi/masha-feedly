<form $FormAttributes data-layout-type="border">
    <div class="panel panel--scrollable flexbox-area-grow cms-content-fields">
        <% if $Message %><p class="message $MessageType" role="status">$Message</p><% end_if %>
        <fieldset class="masha-module-body"><% loop $Fields %>$FieldHolder<% end_loop %></fieldset>
    </div>
    <% if $Actions %><div class="toolbar toolbar--south cms-content-actions cms-content-controls south"><div class="btn-toolbar"><% loop $Actions %>$FieldHolder<% end_loop %></div></div><% end_if %>
</form>
