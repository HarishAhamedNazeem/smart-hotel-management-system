<?php
/**
 * Promotion Banner Component
 * Reusable promotion banner for guest pages
 */
if (!function_exists('getActivePromotions')) {
    require_once __DIR__ . '/../../includes/promotions.php';
}

// Get active promotions
$activePromotions = getActivePromotions();
if (!empty($activePromotions)):
?>
<div class="container" style="margin-top: 20px; margin-bottom: 20px;">
    <div id="promotionsCarousel" class="carousel slide" data-ride="carousel" data-interval="5000">
        <div class="carousel-inner" role="listbox">
            <?php foreach ($activePromotions as $index => $promo): ?>
                <div class="item <?php echo $index === 0 ? 'active' : ''; ?>">
                    <div class="promotion-banner" style="background: linear-gradient(135deg, var(--color-primary-dark) 0%, var(--color-primary) 100%); border: 2px solid var(--color-accent); border-radius: 8px; padding: 25px; text-align: center; box-shadow: 0 8px 24px rgba(61, 44, 141, 0.3); color: white;">
                        <div class="row">
                            <div class="col-md-10 col-md-offset-1">
                                <div style="display: flex; align-items: center; justify-content: center; gap: 20px; flex-wrap: wrap;">
                                    <div style="flex: 1; min-width: 150px;">
                                        <div style="font-size: 42px; font-weight: bold; color: var(--color-accent); line-height: 1;">
                                            <?php echo formatPromotionDiscount($promo); ?>
                                        </div>
                                        <div style="font-size: 13px; color: rgba(255,255,255,0.9); margin-top: 5px;">
                                            Special Offer
                                        </div>
                                    </div>
                                    <div style="flex: 2; min-width: 250px; text-align: left;">
                                        <h3 style="margin: 0 0 8px 0; color: #fff; font-size: 22px; font-weight: 600;">
                                            <?php echo htmlspecialchars($promo['promotion_name']); ?>
                                        </h3>
                                        <?php if (!empty($promo['description'])): ?>
                                            <p style="color: rgba(255,255,255,0.95); margin: 0 0 12px 0; font-size: 15px; line-height: 1.5;">
                                                <?php echo htmlspecialchars($promo['description']); ?>
                                            </p>
                                        <?php endif; ?>
                                        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                                            <div style="font-size: 12px; color: rgba(255,255,255,0.85);">
                                                <i class="fa fa-calendar" style="color: var(--color-accent);"></i> <?php echo formatPromotionDates($promo['start_date'], $promo['end_date']); ?>
                                            </div>
                                            <?php if (!empty($promo['promotion_code'])): ?>
                                                <div style="background: rgba(191, 192, 192, 0.3); padding: 5px 12px; border-radius: 4px; font-size: 12px; font-weight: 600; color: #fff; border: 1px solid var(--color-accent);">
                                                    Code: <?php echo htmlspecialchars($promo['promotion_code']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (count($activePromotions) > 1): ?>
            <a class="left carousel-control" href="#promotionsCarousel" role="button" data-slide="prev" style="background: none; width: 50px;">
                <span class="glyphicon glyphicon-chevron-left" aria-hidden="true" style="color: var(--color-accent); font-size: 30px;"></span>
                <span class="sr-only">Previous</span>
            </a>
            <a class="right carousel-control" href="#promotionsCarousel" role="button" data-slide="next" style="background: none; width: 50px;">
                <span class="glyphicon glyphicon-chevron-right" aria-hidden="true" style="color: var(--color-accent); font-size: 30px;"></span>
                <span class="sr-only">Next</span>
            </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

