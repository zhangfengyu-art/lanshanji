<footer class="footer">
    <div class="container">
        @if(site_mode() === 'B')
            <div class="b-footer-shell">
                <div class="b-footer-columns">
                    <div class="b-footer-brand">
                        <div class="b-footer-brand__title">岚山集</div>
                        <div class="b-footer-brand__sub">跨境代购撮合与资金托管平台</div>
                        <p class="b-footer-brand__desc">我们提供任务发布、承接撮合、资金托管、转寄履约与争议处理的一体化服务。</p>
                    </div>

                    <div class="b-footer-links">
                        <h4>用户服务</h4>
                        <a href="{{ route('b_mode.faq_guidelines') }}">帮助中心</a>
                        <a href="{{ route('procurement.create') }}">发布需求</a>
                        <a href="{{ route('orders.index') }}">我的任务</a>
                        <a href="{{ route('user_addresses.index') }}">地址管理</a>
                    </div>

                    <div class="b-footer-links">
                        <h4>交易说明</h4>
                        <a href="{{ route('b_mode.faq_guidelines') }}">托管说明</a>
                        <a href="{{ route('b_mode.faq_guidelines') }}">履约规则</a>
                        <a href="{{ route('b_mode.faq_guidelines') }}">争议处理</a>
                        <a href="{{ route('b_mode.faq_guidelines') }}">风险提示</a>
                    </div>

                    <div class="b-footer-contact">
                        <h4>平台联系</h4>
                        <div class="b-footer-contact__line">客服邮箱：<a href="mailto:care@superbuy.com">care@superbuy.com</a></div>
                        <div class="b-footer-contact__line">商务合作：<a href="mailto:B2B@superbuy.com">B2B@superbuy.com</a></div>
                        <div class="b-footer-contact__line">7x9 小时服务：+86 19986924711</div>
                    </div>
                </div>

                <div class="b-footer-disclaimer">
                    <p><i class="fa fa-shield" aria-hidden="true"></i> 岚山集：专业的跨境代购撮合与资金托管平台。</p>
                    <p>本平台仅提供代购信息发布与第三方资金托管服务，所有交易均受《跨境代购委托服务协议》约束。</p>
                </div>

                <div class="b-footer-bottom">
                    <span class="b-footer-bottom__item">7x9 小时在线支持</span>
                    <span class="b-footer-bottom__item">委托撮合 · 资金托管 · 履约结算</span>
                    <span class="b-footer-bottom__item">风险提示：请先阅读规则再发起委托</span>
                </div>
            </div>
        @else
            <div aria-label="帮助导航" style="max-width: 620px; margin: 0 auto 12px; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); column-gap: 12px;">
                <a href="{{ route('pages.order_flow') }}" style="display:flex; justify-content:center; align-items:center; width:100%; min-height:34px; padding:6px 10px; font-size:16px; font-weight:600; color:#2f3b4a; border:1px solid #dde3ea; border-radius:6px; background:#f7f9fb; line-height:1.2; text-decoration:none;">下单流程</a>
                <a href="{{ route('pages.change_exchange_return') }}" style="display:flex; justify-content:center; align-items:center; width:100%; min-height:34px; padding:6px 10px; font-size:16px; font-weight:600; color:#2f3b4a; border:1px solid #dde3ea; border-radius:6px; background:#f7f9fb; line-height:1.2; text-decoration:none;">改/换/退</a>
                <a href="{{ route('pages.faq') }}" style="display:flex; justify-content:center; align-items:center; width:100%; min-height:34px; padding:6px 10px; font-size:16px; font-weight:600; color:#2f3b4a; border:1px solid #dde3ea; border-radius:6px; background:#f7f9fb; line-height:1.2; text-decoration:none;">常见问题</a>
            </div>
            <p class="footer-disclaimer text-muted text-center" style="margin-bottom: 0;">
                &copy; 2026 岚山选物. All rights reserved.
            </p>
        @endif
    </div>
</footer>