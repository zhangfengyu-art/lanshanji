<footer class="footer">
    <div class="container">
        @if(site_mode() === 'B')
            <p class="footer-disclaimer text-muted text-center" style="margin-bottom: 6px;">
                <i class="fa fa-shield" aria-hidden="true"></i>
                岚山集：专业的跨境代购撮合与资金托管平台。
            </p>
            <p class="text-muted text-center" style="font-size: 12px; line-height: 1.6; margin: 0 auto; max-width: 860px;">
                本平台仅提供代购信息发布与第三方资金托管服务，所有交易均受《跨境代购委托服务协议》约束。
            </p>
        @else
            <div aria-label="帮助导航" style="max-width: 620px; margin: 0 auto 12px; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); column-gap: 12px;">
                <a href="{{ url('/pages/order-flow.html') }}" style="display:flex; justify-content:center; align-items:center; width:100%; min-height:34px; padding:6px 10px; font-size:16px; font-weight:600; color:#2f3b4a; border:1px solid #dde3ea; border-radius:6px; background:#f7f9fb; line-height:1.2; text-decoration:none;">下单流程</a>
                <a href="{{ url('/pages/change-exchange-return.html') }}" style="display:flex; justify-content:center; align-items:center; width:100%; min-height:34px; padding:6px 10px; font-size:16px; font-weight:600; color:#2f3b4a; border:1px solid #dde3ea; border-radius:6px; background:#f7f9fb; line-height:1.2; text-decoration:none;">改/退规则</a>
                <a href="{{ url('/pages/faq.html') }}" style="display:flex; justify-content:center; align-items:center; width:100%; min-height:34px; padding:6px 10px; font-size:16px; font-weight:600; color:#2f3b4a; border:1px solid #dde3ea; border-radius:6px; background:#f7f9fb; line-height:1.2; text-decoration:none;">常见问题</a>
            </div>
            <p class="footer-disclaimer text-muted text-center" style="margin-bottom: 0;">
                &copy; 2026 岚山选物. All rights reserved.
            </p>
        @endif
    </div>
</footer>