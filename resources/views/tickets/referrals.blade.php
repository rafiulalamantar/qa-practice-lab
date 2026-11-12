@extends('tickets.layout', ['page' => 'referrals', 'sidebar' => true])

@section('nav', true)
@section('tt-title', 'Refer & Earn')

@section('tickets')
<main class="container-xxl px-3 px-lg-4">
    <header class="tt-page-head mb-4">
        <span class="tt-eyebrow"><i class="bi bi-gift"></i> Refer &amp; Earn</span>
        <h1 class="mt-1">Give ৳200, get ৳200</h1>
        <p class="text-secondary mb-0">Invite friends to TicketTrail. When they sign up with your link, you both get booking credit.</p>
    </header>

    <div class="tt-note mb-4" role="note">
        <i class="bi bi-info-circle-fill"></i>
        <div>This is a practice page: invites and credit are stored only in this browser and no email is sent. Use <strong>Simulate sign-up</strong> to see how a joined invite changes your tier.</div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <section class="tt-card p-4 h-100" aria-labelledby="share-title">
                <h2 class="tt-section-title" id="share-title"><i class="bi bi-link-45deg"></i> Your referral link</h2>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <span class="small text-secondary">Your code</span>
                    <span class="tt-ref" id="referral-code" data-testid="referral-code">…</span>
                </div>
                <div class="input-group tt-copy">
                    <input type="text" class="form-control" id="referral-link" data-testid="referral-link" readonly aria-label="Referral link" value="">
                    <button class="btn btn-tt" type="button" id="copy-referral-btn" data-testid="copy-referral-btn"><i class="bi bi-clipboard me-1"></i> <span>Copy link</span></button>
                </div>
                <div class="d-flex flex-wrap gap-2 mt-3" data-testid="share-buttons">
                    <a class="btn btn-sm btn-outline-secondary" id="share-whatsapp" target="_blank" rel="noopener" href="#"><i class="bi bi-whatsapp me-1"></i> WhatsApp</a>
                    <a class="btn btn-sm btn-outline-secondary" id="share-x" target="_blank" rel="noopener" href="#"><i class="bi bi-twitter-x me-1"></i> Post</a>
                    <a class="btn btn-sm btn-outline-secondary" id="share-email" href="#"><i class="bi bi-envelope me-1"></i> Email</a>
                </div>

                <hr class="my-4">

                <h2 class="tt-section-title"><i class="bi bi-send"></i> Invite by email</h2>
                <form id="invite-form" data-testid="invite-form" novalidate>
                    <div class="row g-2 align-items-start">
                        <div class="col-sm tt-field">
                            <label for="invite-email" class="visually-hidden">Friend's email</label>
                            <input id="invite-email" name="email" type="email" class="form-control" placeholder="friend@email.com" autocomplete="off" data-testid="invite-email">
                            <div class="invalid-feedback" data-error-for="email" id="invite-email-error"></div>
                        </div>
                        <div class="col-sm-auto">
                            <button class="btn btn-tt w-100" type="submit" id="invite-btn" data-testid="invite-btn"><i class="bi bi-send me-1"></i> Send invite</button>
                        </div>
                    </div>
                </form>

                <div class="table-responsive mt-3">
                    <table class="table align-middle mb-0 tt-table" id="invites-table" data-testid="invites-table">
                        <thead><tr><th scope="col">Friend</th><th scope="col">Sent</th><th scope="col">Status</th><th scope="col" class="text-end">Actions</th></tr></thead>
                        <tbody id="invites-body"></tbody>
                    </table>
                </div>
                <div class="tt-empty py-4" id="invites-empty" data-testid="invites-empty">
                    <div class="tt-empty-icon"><i class="bi bi-people"></i></div>
                    <h3 class="h6 mb-1">No invites yet</h3>
                    <p class="small text-secondary mb-0">Share your link or send your first invite above.</p>
                </div>
            </section>
        </div>

        <div class="col-lg-5 d-grid gap-4 align-content-start">
            <section class="tt-card p-4" aria-labelledby="stats-title">
                <h2 class="tt-section-title" id="stats-title"><i class="bi bi-bar-chart"></i> Your rewards</h2>
                <div class="row g-3 text-center">
                    <div class="col-4"><div class="tt-mini-stat"><strong id="ref-sent" data-testid="ref-sent">0</strong><span>Invited</span></div></div>
                    <div class="col-4"><div class="tt-mini-stat"><strong id="ref-joined" data-testid="ref-joined">0</strong><span>Joined</span></div></div>
                    <div class="col-4"><div class="tt-mini-stat"><strong id="ref-credit" data-testid="ref-credit">৳0</strong><span>Earned</span></div></div>
                </div>
            </section>

            <section class="tt-card p-4" aria-labelledby="tier-title">
                <h2 class="tt-section-title" id="tier-title"><i class="bi bi-award"></i> Affiliate tier</h2>
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="tt-tier" id="affiliate-tier" data-testid="affiliate-tier" data-tier="starter">Starter</span>
                    <span class="small text-secondary" id="affiliate-next" data-testid="affiliate-next"></span>
                </div>
                <div class="tt-progress" aria-hidden="true"><span id="affiliate-progress"></span></div>
                <ol class="tt-tiers mt-3" data-testid="affiliate-tiers">
                    <li data-tier="starter"><strong>Starter</strong><span>0 joined · ৳200 per friend</span></li>
                    <li data-tier="bronze"><strong>Bronze</strong><span>3 joined · ৳250 per friend</span></li>
                    <li data-tier="silver"><strong>Silver</strong><span>5 joined · ৳300 per friend</span></li>
                    <li data-tier="gold"><strong>Gold</strong><span>10 joined · ৳400 per friend + Pro</span></li>
                </ol>
            </section>

            <section class="tt-card p-4" aria-labelledby="faq-title">
                <h2 class="tt-section-title" id="faq-title"><i class="bi bi-question-circle"></i> How it works</h2>
                <div class="accordion accordion-flush tt-faq" id="referral-faq">
                    <div class="accordion-item">
                        <h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq-1" aria-expanded="false" aria-controls="faq-1">When do I get my credit?</button></h3>
                        <div id="faq-1" class="accordion-collapse collapse" data-bs-parent="#referral-faq"><div class="accordion-body small text-secondary">As soon as your friend registers with your link. Credit applies to your next booking.</div></div>
                    </div>
                    <div class="accordion-item">
                        <h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq-2" aria-expanded="false" aria-controls="faq-2">Is there a limit?</button></h3>
                        <div id="faq-2" class="accordion-collapse collapse" data-bs-parent="#referral-faq"><div class="accordion-body small text-secondary">You can have up to 10 pending invites at a time. Resending an invite is limited to once every 30 seconds.</div></div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</main>
@endsection
