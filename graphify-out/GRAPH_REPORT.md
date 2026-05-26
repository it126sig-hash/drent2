# Graph Report - frontend  (2026-05-23)

## Corpus Check
- 154 files · ~125,256 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 971 nodes · 1176 edges · 115 communities (100 shown, 15 thin omitted)
- Extraction: 96% EXTRACTED · 4% INFERRED · 0% AMBIGUOUS · INFERRED: 51 edges (avg confidence: 0.84)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- [[_COMMUNITY_Booking Detail Workflow|Booking Detail Workflow]]
- [[_COMMUNITY_Booking Calendar Controls|Booking Calendar Controls]]
- [[_COMMUNITY_Booking Create Flow|Booking Create Flow]]
- [[_COMMUNITY_Booking API Actions|Booking API Actions]]
- [[_COMMUNITY_Physical Check Annotation|Physical Check Annotation]]
- [[_COMMUNITY_Receivables Payments|Receivables Payments]]
- [[_COMMUNITY_Frontend Dependencies|Frontend Dependencies]]
- [[_COMMUNITY_Account Balance Adjustments|Account Balance Adjustments]]
- [[_COMMUNITY_Customer Detail Timeline|Customer Detail Timeline]]
- [[_COMMUNITY_Pricing Cost Comparison|Pricing Cost Comparison]]
- [[_COMMUNITY_Booking Extend Actions|Booking Extend Actions]]
- [[_COMMUNITY_Driver Fund Review|Driver Fund Review]]
- [[_COMMUNITY_Dashboard Overview|Dashboard Overview]]
- [[_COMMUNITY_Frontend Visual Assets|Frontend Visual Assets]]
- [[_COMMUNITY_Capacitor App Entrypoint|Capacitor App Entrypoint]]
- [[_COMMUNITY_Android Splash Assets|Android Splash Assets]]
- [[_COMMUNITY_Driver API Composable|Driver API Composable]]
- [[_COMMUNITY_Booking Unit Mapping|Booking Unit Mapping]]
- [[_COMMUNITY_Invoice Detail View|Invoice Detail View]]
- [[_COMMUNITY_App Router Permission|App Router Permission]]
- [[_COMMUNITY_Unit API Composable|Unit API Composable]]
- [[_COMMUNITY_Booking Period Helpers|Booking Period Helpers]]
- [[_COMMUNITY_iOS App Delegate|iOS App Delegate]]
- [[_COMMUNITY_Android Launcher Icons|Android Launcher Icons]]
- [[_COMMUNITY_User API Composable|User API Composable]]
- [[_COMMUNITY_Member API Composable|Member API Composable]]
- [[_COMMUNITY_Core API Clients|Core API Clients]]
- [[_COMMUNITY_Customer API Composable|Customer API Composable]]
- [[_COMMUNITY_City API Composable|City API Composable]]
- [[_COMMUNITY_Rolling Booking Dates|Rolling Booking Dates]]
- [[_COMMUNITY_Shared Save Handlers|Shared Save Handlers]]
- [[_COMMUNITY_Dialog Form State|Dialog Form State]]
- [[_COMMUNITY_Cost Type API|Cost Type API]]
- [[_COMMUNITY_Payment Account API|Payment Account API]]
- [[_COMMUNITY_Pricing Package API|Pricing Package API]]
- [[_COMMUNITY_Rental Owner API|Rental Owner API]]
- [[_COMMUNITY_Booking Total Helpers|Booking Total Helpers]]
- [[_COMMUNITY_Calendar Filters|Calendar Filters]]
- [[_COMMUNITY_iOS Brand Assets|iOS Brand Assets]]
- [[_COMMUNITY_Booking Calendar Layout|Booking Calendar Layout]]
- [[_COMMUNITY_Booking Payment Submit|Booking Payment Submit]]
- [[_COMMUNITY_Public Bill View|Public Bill View]]
- [[_COMMUNITY_Public Check Media|Public Check Media]]
- [[_COMMUNITY_Vehicle Diagram Assets|Vehicle Diagram Assets]]
- [[_COMMUNITY_Package Price Handlers|Package Price Handlers]]
- [[_COMMUNITY_iOS Asset Metadata|iOS Asset Metadata]]
- [[_COMMUNITY_Asset Contents Metadata|Asset Contents Metadata]]
- [[_COMMUNITY_Check Form Initialization|Check Form Initialization]]
- [[_COMMUNITY_iOS Contents Metadata|iOS Contents Metadata]]
- [[_COMMUNITY_Capacitor Config|Capacitor Config]]
- [[_COMMUNITY_Unit Selection Handlers|Unit Selection Handlers]]
- [[_COMMUNITY_Dialog Close Guard|Dialog Close Guard]]
- [[_COMMUNITY_List Pagination Search|List Pagination Search]]
- [[_COMMUNITY_Debt Payment Dialogs|Debt Payment Dialogs]]
- [[_COMMUNITY_Public Bill Sharing|Public Bill Sharing]]
- [[_COMMUNITY_Form Submit Validation|Form Submit Validation]]
- [[_COMMUNITY_Android Main Activity|Android Main Activity]]
- [[_COMMUNITY_Android Instrumented Test|Android Instrumented Test]]
- [[_COMMUNITY_Android Unit Test|Android Unit Test]]
- [[_COMMUNITY_Detail Total Sewa|Detail Total Sewa]]
- [[_COMMUNITY_Annotation Drawing|Annotation Drawing]]
- [[_COMMUNITY_Annotation Pointer Moves|Annotation Pointer Moves]]
- [[_COMMUNITY_Form Step Navigation|Form Step Navigation]]
- [[_COMMUNITY_Signature Pad|Signature Pad]]
- [[_COMMUNITY_Return Request Menu|Return Request Menu]]
- [[_COMMUNITY_Priced Details Check|Priced Details Check]]
- [[_COMMUNITY_Calendar Date Normalize|Calendar Date Normalize]]
- [[_COMMUNITY_Detail Navigation|Detail Navigation]]
- [[_COMMUNITY_Status Filter Toggle|Status Filter Toggle]]
- [[_COMMUNITY_Filter Reset Actions|Filter Reset Actions]]
- [[_COMMUNITY_Payment Date Submit|Payment Date Submit]]
- [[_COMMUNITY_VS Code Extensions|VS Code Extensions]]

## God Nodes (most connected - your core abstractions)
1. `loadBooking()` - 15 edges
2. `Centered Blue Geometric Mark on White Splash Background` - 11 edges
3. `Android Density Scale Splash Variants` - 10 edges
4. `AppDelegate` - 9 edges
5. `mapUnitOption()` - 8 edges
6. `logFillActivity()` - 8 edges
7. `handleSubmit()` - 7 edges
8. `applyPricingPackage()` - 7 edges
9. `applyDefaultTime()` - 7 edges
10. `Social And Documentation Icon Sprite` - 7 edges

## Surprising Connections (you probably didn't know these)
- `handleSubmit()` --calls--> `updateBooking()`  [INFERRED]
  src/views/bookings/BookingCreateView.vue → src/api/booking.js
- `save()` --calls--> `update()`  [INFERRED]
  src/views/master/CityListView.vue → src/api/member.js
- `save()` --calls--> `update()`  [INFERRED]
  src/views/master/PaymentAccountListView.vue → src/api/member.js
- `save()` --calls--> `update()`  [INFERRED]
  src/views/master/PricingPackageListView.vue → src/api/member.js
- `requestPhysicalCheckFromBooking()` --calls--> `requestPhysicalCheck()`  [INFERRED]
  src/views/bookings/BookingDetailView.vue → src/api/physicalCheck.js

## Hyperedges (group relationships)
- **Frontend App Bootstrap** — index_frontend_html_entrypoint, index_app_mount_element, index_main_js_module [INFERRED 0.85]
- **Vue Vite Developer Guidance** — readme_vue_3_vite_template, readme_script_setup_sfcs, readme_vue_ide_support [EXTRACTED 1.00]
- **Capacitor SPM Dependency Hosting** — capapp_spm_package, capapp_spm_dependencies, capapp_capacitor_project [EXTRACTED 1.00]
- **Android Landscape Splash Density Set** — splash_land_hdpi, splash_land_mdpi, splash_land_xhdpi, splash_land_xxhdpi, splash_land_xxxhdpi [EXTRACTED 1.00]
- **Android Portrait Splash Density Set** — splash_port_hdpi, splash_port_mdpi, splash_port_xhdpi, splash_port_xxhdpi, splash_port_xxxhdpi [EXTRACTED 1.00]
- **Android Splash Shared Visual Identity** — splash_default_landscape_mdpi, splash_land_hdpi, splash_land_mdpi, splash_land_xhdpi, splash_land_xxhdpi, splash_land_xxxhdpi, splash_port_hdpi, splash_port_mdpi, splash_port_xhdpi, splash_port_xxhdpi, splash_port_xxxhdpi, splash_centered_blue_mark_white_background [EXTRACTED 1.00]
- **Android Launcher Icon Variants** — ic_launcher_standard_launcher_icon, ic_launcher_foreground_adaptive_launcher_artwork, ic_launcher_round_launcher_icon [EXTRACTED 1.00]
- **Android Launcher Density Buckets** — mipmap_mdpi_launcher_density_set, mipmap_hdpi_launcher_density_set, mipmap_xhdpi_launcher_density_set, mipmap_xxhdpi_launcher_density_set, mipmap_xxxhdpi_launcher_density_set [EXTRACTED 1.00]
- **Launcher Visual Identity** — launcher_icon_blue_geometric_mark, launcher_icon_white_grid_background, ic_launcher_standard_launcher_icon, ic_launcher_round_launcher_icon, ic_launcher_foreground_adaptive_launcher_artwork [INFERRED 0.95]
- **iOS Branding Asset Set** — appicon_512_2x_drent_app_icon, splash_2732_1_drent_splash_screen, splash_2732_2_drent_splash_screen, splash_2732_base_drent_splash_screen, drent_blue_angular_logo_mark [INFERRED 0.85]
- **Public Browser And Sprite Icons** — frontend_public_static_assets, favicon_vite_lightning_mark, icons_social_icon_sprite [EXTRACTED 1.00]
- **Social Sprite Symbol Set** — icons_social_icon_sprite, icons_bluesky_icon, icons_discord_icon, icons_documentation_icon, icons_github_icon, icons_social_user_badge_icon, icons_x_icon [EXTRACTED 1.00]
- **Frontend Source Visual Assets** — frontend_src_asset_bundle, fuel_gauge_vehicle_fuel_level_reference, hero_layered_purple_platforms, vite_vite_logo_parenthesized, vue_vue_logo [EXTRACTED 1.00]
- **Vue Vite Scaffold Identity Assets** — frontend_vue_vite_scaffold_assets, favicon_vite_lightning_mark, vite_vite_logo_parenthesized, vue_vue_logo [INFERRED 0.85]
- **Four View Vehicle Diagram Assets** — car_front_front_view_vehicle_diagram, car_back_rear_view_vehicle_diagram, car_left_left_side_vehicle_diagram, car_right_right_side_vehicle_diagram [EXTRACTED 1.00]
- **Physical Check Body Map Views** — car_front_front_view_vehicle_diagram, car_back_rear_view_vehicle_diagram, car_left_left_side_vehicle_diagram, car_right_right_side_vehicle_diagram, car_assets_physical_check_body_diagram_ui [INFERRED 0.85]

## Communities (115 total, 15 thin omitted)

### Community 0 - "Booking Detail Workflow"
Cohesion: 0.02
Nodes (72): activeDetails, activePayments, additionalCostForm, additionalTypeOptions, batalForm, billableDetails, bookingCalculatedTagihan, bookingSisaTagihan (+64 more)

### Community 1 - "Booking Calendar Controls"
Cohesion: 0.04
Nodes (25): activeStatusOptions, baseCalendarUnits, bookingContextMenu, calendarOwnerOptions, calendarUnits, closedStatusOptions, closedTabStatusValues, contextMenuItems (+17 more)

### Community 2 - "Booking Create Flow"
Cohesion: 0.08
Nodes (30): addRentalDuration(), applyDefaultTime(), applyDirectWaitingListBookingDefaults(), applyWaitingPackage(), buildWaitingListPayload(), cityOptions, createAllInPackageCost(), customerOptions (+22 more)

### Community 4 - "Physical Check Annotation"
Cohesion: 0.06
Nodes (23): activeDetail, activeGalleryPhoto, annotatorCanvas, annotatorPhoto, annotatorSource, annotatorVisible, bookingId, canSubmit (+15 more)

### Community 5 - "Receivables Payments"
Cohesion: 0.06
Nodes (11): canGenerateBill, debtGroups, ownerOptions, paymentAccountOptions, paymentPreviewItems, paymentTarget, sanitized, selectedBookingCodes (+3 more)

### Community 6 - "Frontend Dependencies"
Cohesion: 0.07
Nodes (29): dependencies, autoprefixer, axios, @capacitor/android, @capacitor/core, @capacitor/ios, date-fns, pinia (+21 more)

### Community 8 - "Account Balance Adjustments"
Cohesion: 0.10
Nodes (13): activeAccountCount, adjustForm, canAdjust, form, formatCurrency(), formErrors, isMobile, saving (+5 more)

### Community 9 - "Customer Detail Timeline"
Cohesion: 0.11
Nodes (6): canDelete, detailTimeline, end, hasRiskCustomer, rentalHistory, start

### Community 10 - "Pricing Cost Comparison"
Cohesion: 0.11
Nodes (16): codeCompare, costs, costType, dateA, dateB, deposits, detailIds, detailRows (+8 more)

### Community 11 - "Booking Extend Actions"
Cohesion: 0.14
Nodes (17): addAdditionalCost(), extend(), requestPhysicalCheck(), formatLocalDateTime(), loadBooking(), requestPhysicalCheckFromBooking(), showActionError(), submitAdditionalCost() (+9 more)

### Community 12 - "Driver Fund Review"
Cohesion: 0.14
Nodes (7): activeFunds, handleAcceptFund(), pastSchedules, rejectedExpenses, reload(), submitDriverExpense(), upcomingSchedules

### Community 14 - "Dashboard Overview"
Cohesion: 0.12
Nodes (7): activeLeaderboard, activeLeaderboardStatus, alerts, armadaStatus, bookingToday, finance, leaderboards

### Community 15 - "Frontend Visual Assets"
Cohesion: 0.16
Nodes (15): Vite Lightning Favicon, Frontend Public Static Assets, Frontend Source Asset Bundle, Vue Vite Scaffold Assets, Vehicle Fuel Gauge Reference Image, Layered Purple Hero Graphic, Bluesky Icon Symbol, Discord Icon Symbol (+7 more)

### Community 16 - "Capacitor App Entrypoint"
Cohesion: 0.17
Nodes (15): Capacitor Project, Do Not Modify CapApp-SPM Contents, SPM Dependencies, CapApp-SPM Package, CapApp-SPM README, App Mount Element, Favicon SVG, Frontend HTML Entrypoint (+7 more)

### Community 17 - "Android Splash Assets"
Cohesion: 0.30
Nodes (15): Android Density Scale Splash Variants, Android Landscape Splash Variants, Android Portrait Splash Variants, Centered Blue Geometric Mark on White Splash Background, Default Android Splash PNG, Landscape HDPI Android Splash PNG, Landscape MDPI Android Splash PNG, Landscape XHDPI Android Splash PNG (+7 more)

### Community 18 - "Driver API Composable"
Cohesion: 0.18
Nodes (8): createDriver(), deleteDriver(), fetchDrivers(), updateDriver(), updateDriverBalance(), driverOptions, fetchDriverOptions(), isSaveDisabled

### Community 19 - "Booking Unit Mapping"
Cohesion: 0.13
Nodes (15): mapUnitOption(), normalizeSearch(), unitStatusMeta(), applyRollingOldDetail(), cloneDetailCosts(), getExtendStartDate(), getInitialBookingDetail(), onHandle() (+7 more)

### Community 20 - "Invoice Detail View"
Cohesion: 0.14
Nodes (7): customerAddressLines, customerContactLines, customerName, filteredPaymentAccounts, invoiceItems, paymentHistory, primaryBooking

### Community 21 - "App Router Permission"
Cohesion: 0.19
Nodes (7): currentPath, itemPath, usePermission(), auth, router, app, useAuthStore

### Community 23 - "Unit API Composable"
Cohesion: 0.26
Nodes (8): createUnit(), deleteUnit(), deleteUnitPhoto(), getUnits(), updateUnit(), uploadUnitPhoto(), searchUnits(), searchUnits()

### Community 24 - "Booking Period Helpers"
Cohesion: 0.20
Nodes (12): formatPackage(), getDisplayDetail(), getDriverInfo(), getEarliestDate(), getLateInfo(), getLatestDate(), getPeriodEndDate(), getPeriodStartDate() (+4 more)

### Community 25 - "iOS App Delegate"
Cohesion: 0.20
Nodes (3): AppDelegate, UIApplicationDelegate, UIResponder

### Community 26 - "Android Launcher Icons"
Cohesion: 0.36
Nodes (10): Adaptive Launcher Foreground Artwork, Round Android Launcher Icon, Standard Android Launcher Icon, Blue Geometric Launcher Mark, White Diagonal Grid Background, HDPI Launcher Icon Density Set, MDPI Launcher Icon Density Set, XHDPI Launcher Icon Density Set (+2 more)

### Community 27 - "User API Composable"
Cohesion: 0.33
Nodes (6): createUser(), deleteUser(), getRoles(), getUsers(), resetUserPassword(), updateUser()

### Community 28 - "Member API Composable"
Cohesion: 0.24
Nodes (3): get(), getExtensions(), list()

### Community 31 - "Customer API Composable"
Cohesion: 0.36
Nodes (6): createCustomer(), deleteCustomer(), fetchCustomer(), fetchCustomers(), updateCustomer(), searchCustomers()

### Community 32 - "City API Composable"
Cohesion: 0.33
Nodes (5): createCity(), deleteCity(), fetchCities(), updateCity(), loadFilterOptions()

### Community 33 - "Rolling Booking Dates"
Cohesion: 0.33
Nodes (9): addRentalDuration(), applyDefaultTime(), getNextRentalStartDate(), setDetailReturnDate(), setDetailStartDate(), submitRolling(), syncDetailReturnDate(), syncRollingNewSchedule() (+1 more)

### Community 34 - "Shared Save Handlers"
Cohesion: 0.22
Nodes (5): update(), saveCustomer(), save(), save(), save()

### Community 35 - "Dialog Form State"
Cohesion: 0.22
Nodes (5): canDelete, form, formErrors, saving, showDialog

### Community 36 - "Cost Type API"
Cohesion: 0.39
Nodes (4): createCostType(), deleteCostType(), getCostTypes(), updateCostType()

### Community 38 - "Payment Account API"
Cohesion: 0.39
Nodes (4): createPaymentAccount(), deletePaymentAccount(), getPaymentAccounts(), updatePaymentAccount()

### Community 39 - "Pricing Package API"
Cohesion: 0.39
Nodes (4): createPricingPackage(), deletePricingPackage(), getPricingPackages(), updatePricingPackage()

### Community 40 - "Rental Owner API"
Cohesion: 0.39
Nodes (4): createRentalOwner(), deleteRentalOwner(), getRentalOwners(), updateRentalOwner()

### Community 41 - "Booking Total Helpers"
Cohesion: 0.32
Nodes (8): detailBillableCostTotal(), detailConsumerBill(), detailCostTotal(), detailRentalSubtotal(), detailUnitPriceTotal(), detailUnitTotalWithCosts(), getBillableCostTotal(), sumCosts()

### Community 42 - "Calendar Filters"
Cohesion: 0.36
Nodes (8): applyFilters(), getActiveTabStatusFilter(), getClosedTabStatusFilter(), loadCalendarData(), loadData(), nextMonth(), prevMonth(), resetFilters()

### Community 44 - "iOS Brand Assets"
Cohesion: 0.57
Nodes (7): DRENT iOS App Icon 512@2x, iOS AppIcon Asset Set, DRENT Blue Angular Logo Mark, DRENT Splash Screen Variant 1, DRENT Splash Screen Variant 2, DRENT Splash Screen Base Variant, iOS Splash Launch Screen Asset Set

### Community 45 - "Booking Calendar Layout"
Cohesion: 0.29
Nodes (6): d, dEnd, dow, dStart, span, startOffset

### Community 46 - "Booking Payment Submit"
Cohesion: 0.29
Nodes (7): approveVoidRequest(), detailPricingModeLabel(), detailUnitPriceDescription(), formatCurrency(), formatSignedCostAmount(), getSignedCostAmount(), submitPayment()

### Community 48 - "Public Check Media"
Cohesion: 0.29
Nodes (7): compressImage(), logFillActivity(), onPhotoSelect(), removePhoto(), saveAnnotation(), sendOtp(), setFuelMarker()

### Community 49 - "Vehicle Diagram Assets"
Cohesion: 0.73
Nodes (6): Four-View Vehicle Diagram Asset Set, Vehicle Physical-Check Body Diagram UI, Car Back Rear View SVG, Car Front View SVG, Car Left Side View SVG, Car Right Side View SVG

### Community 50 - "Package Price Handlers"
Cohesion: 0.33
Nodes (6): applyPricingPackage(), onDetailPackageChange(), onExtendPackageChange(), onHandlePackageChange(), onRollingNewPackageChange(), onRollingOldPackageChange()

### Community 52 - "iOS Asset Metadata"
Cohesion: 0.40
Nodes (4): images, info, author, version

### Community 53 - "Asset Contents Metadata"
Cohesion: 0.40
Nodes (4): images, info, author, version

### Community 54 - "Check Form Initialization"
Cohesion: 0.40
Nodes (5): hydrateExistingCheck(), initializeChecklist(), loadData(), logStepOpened(), resolveMediaUrl()

### Community 56 - "iOS Contents Metadata"
Cohesion: 0.50
Nodes (3): info, author, version

### Community 57 - "Capacitor Config"
Cohesion: 0.50
Nodes (3): appId, appName, webDir

### Community 58 - "Unit Selection Handlers"
Cohesion: 0.50
Nodes (4): onHandleUnitChange(), onRollingOldUnitChange(), onUnitChange(), onUnitSelect()

### Community 59 - "Dialog Close Guard"
Cohesion: 0.50
Nodes (4): closeDialogSilently(), getDialogStateMap(), onDialogVisibleChange(), requestCloseDialog()

### Community 60 - "List Pagination Search"
Cohesion: 0.50
Nodes (4): fetchData(), onPageChange(), onSearch(), resetFilters()

### Community 61 - "Debt Payment Dialogs"
Cohesion: 0.67
Nodes (4): ensurePaymentAccounts(), openDebtPaymentDialog(), openDirectPayment(), openPaymentDialog()

### Community 62 - "Public Bill Sharing"
Cohesion: 0.50
Nodes (4): copyToClipboard(), openPublicBill(), publicBillUrl(), sendBill()

### Community 63 - "Form Submit Validation"
Cohesion: 0.50
Nodes (4): buildPayload(), buildPublicSignaturePayload(), submit(), validateForm()

### Community 67 - "Detail Total Sewa"
Cohesion: 0.67
Nodes (3): getDetailCostTotal(), getDetailRentalSubtotal(), getDetailTotalSewa()

### Community 69 - "Annotation Drawing"
Cohesion: 0.67
Nodes (3): drawAnnotatorImage(), openAnnotator(), resetAnnotation()

### Community 70 - "Annotation Pointer Moves"
Cohesion: 0.67
Nodes (3): canvasPoint(), moveAnnotating(), startAnnotating()

### Community 71 - "Form Step Navigation"
Cohesion: 0.67
Nodes (3): goToStep(), nextStep(), validateStep()

## Knowledge Gaps
- **260 isolated node(s):** `appId`, `appName`, `webDir`, `name`, `private` (+255 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **15 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `loadFilterOptions()` connect `City API Composable` to `Booking Calendar Controls`?**
  _High betweenness centrality (0.086) - this node is a cross-community bridge._
- **Why does `update()` connect `Shared Save Handlers` to `Member API Composable`?**
  _High betweenness centrality (0.071) - this node is a cross-community bridge._
- **What connects `appId`, `appName`, `webDir` to the rest of the system?**
  _261 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Booking Detail Workflow` be split into smaller, more focused modules?**
  _Cohesion score 0.019417475728155338 - nodes in this community are weakly interconnected._
- **Should `Booking Calendar Controls` be split into smaller, more focused modules?**
  _Cohesion score 0.043478260869565216 - nodes in this community are weakly interconnected._
- **Should `Booking Create Flow` be split into smaller, more focused modules?**
  _Cohesion score 0.07682926829268293 - nodes in this community are weakly interconnected._
- **Should `Booking API Actions` be split into smaller, more focused modules?**
  _Cohesion score 0.05 - nodes in this community are weakly interconnected._