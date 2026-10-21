<div class="cms-content fill-height flexbox-area-grow center cms-tabset $BaseCSSClasses masha-module-shell masha-module-shell--feedly" data-layout-type="border" data-pjax-fragment="Content" id="ModelAdmin">
    <header class="cms-content-header north masha-module-header">
        <div class="masha-module-header__row">
            <div class="masha-module-header__identity"><img src="$ModuleIcon.ATT" alt="" width="40" height="40"><h1>Masha:Feedly</h1></div>
        </div>
        <div class="cms-content-header-tabs cms-tabset-nav-primary ss-ui-tabs-nav">
            <ul class="cms-tabset-nav-primary">
                <% loop $ManagedModelTabs %><li class="$LinkOrCurrent<% if $LinkOrCurrent == 'current' %> ui-tabs-active<% end_if %>"><a href="$Link.ATT" class="cms-panel-link">$Title.XML</a></li><% end_loop %>
            </ul>
        </div>
    </header>
    <div class="cms-content-fields center ui-widget-content cms-panel-padded fill-height flexbox-area-grow" data-layout-type="border">
        $Tools
        <div class="cms-content-view">$EditForm</div>
    </div>
</div>
