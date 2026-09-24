<f:asset.css identifier="{{ extKey }}-soul" href="EXT:{{ extKey }}/Resources/Public/Soul/soul.css" />
<f:asset.css identifier="{{ extKey }}-layout" href="EXT:{{ extKey }}/Resources/Public/Css/layout.css" />

<a class="sds-skip sds-btn sds-btn--secondary" href="#main-content">Skip to content</a>
<div class="sds-shell">
    <f:render partial="Header" arguments="{_all}" />
    <div class="sds-body">
        <main class="sds-body__main" id="main-content">
            <f:render section="Main" />
        </main>
    </div>
    <f:render partial="Footer" arguments="{_all}" />
</div>
