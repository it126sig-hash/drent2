# Graph Report - ./frontend  (2026-05-30)

## Corpus Check
- 176 files · ~153,349 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 1204 nodes · 1464 edges · 144 communities (120 shown, 24 thin omitted)
- Extraction: 95% EXTRACTED · 5% INFERRED · 0% AMBIGUOUS · INFERRED: 73 edges (avg confidence: 0.83)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- [[_COMMUNITY_Booking Detail Management|Booking Detail Management]]
- [[_COMMUNITY_Booking List & Calendar|Booking List & Calendar]]
- [[_COMMUNITY_Receivable & Invoice|Receivable & Invoice]]
- [[_COMMUNITY_Rent-to-Rent Finance|Rent-to-Rent Finance]]
- [[_COMMUNITY_Package Dependencies|Package Dependencies]]
- [[_COMMUNITY_Physical Check Form|Physical Check Form]]
- [[_COMMUNITY_Dashboard View|Dashboard View]]
- [[_COMMUNITY_Booking Creation|Booking Creation]]
- [[_COMMUNITY_App Layout & Navigation|App Layout & Navigation]]
- [[_COMMUNITY_Customer Management|Customer Management]]
- [[_COMMUNITY_Operational Cost View|Operational Cost View]]
- [[_COMMUNITY_Payment Account Master|Payment Account Master]]
- [[_COMMUNITY_Booking Detail Actions|Booking Detail Actions]]
- [[_COMMUNITY_Public Invoice View|Public Invoice View]]
- [[_COMMUNITY_Unit API|Unit API]]
- [[_COMMUNITY_Driver API|Driver API]]
- [[_COMMUNITY_Driver Operational View|Driver Operational View]]
- [[_COMMUNITY_Branch Form Dialog|Branch Form Dialog]]
- [[_COMMUNITY_Auth & Permissions API|Auth & Permissions API]]
- [[_COMMUNITY_Booking Rental Duration|Booking Rental Duration]]
- [[_COMMUNITY_Capacitor App Config|Capacitor App Config]]
- [[_COMMUNITY_Android Splash Screens|Android Splash Screens]]
- [[_COMMUNITY_Branch List View|Branch List View]]
- [[_COMMUNITY_Booking List Helpers|Booking List Helpers]]
- [[_COMMUNITY_Supervisor Requests|Supervisor Requests]]
- [[_COMMUNITY_Booking List Filters|Booking List Filters]]
- [[_COMMUNITY_City API|City API]]
- [[_COMMUNITY_Member API|Member API]]
- [[_COMMUNITY_iOS App Delegate|iOS App Delegate]]
- [[_COMMUNITY_User API|User API]]
- [[_COMMUNITY_Android Launcher Icons|Android Launcher Icons]]
- [[_COMMUNITY_Customer API|Customer API]]
- [[_COMMUNITY_City List View|City List View]]
- [[_COMMUNITY_Booking Unit Selection|Booking Unit Selection]]
- [[_COMMUNITY_Transaction Report|Transaction Report]]
- [[_COMMUNITY_Branch API|Branch API]]
- [[_COMMUNITY_Pricing Package API|Pricing Package API]]
- [[_COMMUNITY_Booking Cost Totals|Booking Cost Totals]]
- [[_COMMUNITY_Rental Owner API|Rental Owner API]]
- [[_COMMUNITY_Cost Type API|Cost Type API]]
- [[_COMMUNITY_Payment Account API|Payment Account API]]
- [[_COMMUNITY_Booking Search Filters|Booking Search Filters]]
- [[_COMMUNITY_Booking Package Pricing|Booking Package Pricing]]
- [[_COMMUNITY_Entity CRUD Operations|Entity CRUD Operations]]
- [[_COMMUNITY_Booking Price Display|Booking Price Display]]
- [[_COMMUNITY_Booking Detail Filters|Booking Detail Filters]]
- [[_COMMUNITY_Public R2R Bill View|Public R2R Bill View]]
- [[_COMMUNITY_Public Brand Assets|Public Brand Assets]]
- [[_COMMUNITY_iOS App Icons|iOS App Icons]]
- [[_COMMUNITY_Booking Calendar Grid|Booking Calendar Grid]]
- [[_COMMUNITY_Booking Time Defaults|Booking Time Defaults]]
- [[_COMMUNITY_Physical Check Photos|Physical Check Photos]]
- [[_COMMUNITY_Vehicle Diagram Assets|Vehicle Diagram Assets]]
- [[_COMMUNITY_Frontend Asset Bundle|Frontend Asset Bundle]]
- [[_COMMUNITY_Booking Submit Flow|Booking Submit Flow]]
- [[_COMMUNITY_Tenant API|Tenant API]]
- [[_COMMUNITY_Pricing Package View|Pricing Package View]]
- [[_COMMUNITY_iOS Icon Asset Config|iOS Icon Asset Config]]
- [[_COMMUNITY_Booking Package Change|Booking Package Change]]
- [[_COMMUNITY_iOS Splash Config|iOS Splash Config]]
- [[_COMMUNITY_Physical Check Init|Physical Check Init]]
- [[_COMMUNITY_iOS Asset Metadata|iOS Asset Metadata]]
- [[_COMMUNITY_Booking Dialog State|Booking Dialog State]]
- [[_COMMUNITY_Unit Schedule Check|Unit Schedule Check]]
- [[_COMMUNITY_Capacitor Config|Capacitor Config]]
- [[_COMMUNITY_R2R Payment Dialog|R2R Payment Dialog]]
- [[_COMMUNITY_R2R Public Bill Link|R2R Public Bill Link]]
- [[_COMMUNITY_Physical Check Submit|Physical Check Submit]]
- [[_COMMUNITY_Android Instrumented Test|Android Instrumented Test]]
- [[_COMMUNITY_Android Main Activity|Android Main Activity]]
- [[_COMMUNITY_Booking Unit Change|Booking Unit Change]]
- [[_COMMUNITY_Android Unit Test|Android Unit Test]]
- [[_COMMUNITY_Booking Cost Subtotals|Booking Cost Subtotals]]
- [[_COMMUNITY_Booking City Search|Booking City Search]]
- [[_COMMUNITY_Photo Annotator|Photo Annotator]]
- [[_COMMUNITY_Annotator Canvas|Annotator Canvas]]
- [[_COMMUNITY_Physical Check Steps|Physical Check Steps]]
- [[_COMMUNITY_Booking Payment Dialog|Booking Payment Dialog]]
- [[_COMMUNITY_R2R List Filters|R2R List Filters]]
- [[_COMMUNITY_R2R Payment Confirm|R2R Payment Confirm]]
- [[_COMMUNITY_R2R Payment Submit|R2R Payment Submit]]
- [[_COMMUNITY_Signature Pad|Signature Pad]]
- [[_COMMUNITY_Booking Price Check|Booking Price Check]]
- [[_COMMUNITY_Calendar Date Normalize|Calendar Date Normalize]]
- [[_COMMUNITY_Booking Context Menu|Booking Context Menu]]
- [[_COMMUNITY_Booking Row Navigate|Booking Row Navigate]]
- [[_COMMUNITY_VS Code Extensions|VS Code Extensions]]
- [[_COMMUNITY_Launcher Foreground mdpi|Launcher Foreground mdpi]]
- [[_COMMUNITY_Launcher Round mdpi|Launcher Round mdpi]]
- [[_COMMUNITY_Launcher Foreground xhdpi|Launcher Foreground xhdpi]]
- [[_COMMUNITY_Launcher Round xhdpi|Launcher Round xhdpi]]
- [[_COMMUNITY_Launcher Foreground xxhdpi|Launcher Foreground xxhdpi]]
- [[_COMMUNITY_Launcher Round xxhdpi|Launcher Round xxhdpi]]
- [[_COMMUNITY_Launcher Round xxxhdpi|Launcher Round xxxhdpi]]

## God Nodes (most connected - your core abstractions)
1. `loadBooking()` - 16 edges
2. `Centered Blue Geometric Mark on White Splash Background` - 11 edges
3. `Android Density Scale Splash Variants` - 10 edges
4. `AppDelegate` - 9 edges
5. `mapUnitOption()` - 9 edges
6. `openDetailDialog()` - 9 edges
7. `debounceSearch()` - 8 edges
8. `logFillActivity()` - 8 edges
9. `update()` - 7 edges
10. `printPaymentReceipt()` - 7 edges

## Surprising Connections (you probably didn't know these)
- `save()` --calls--> `update()`  [INFERRED]
  src/views/master/PricingPackageListView.vue → src/api/member.js
- `handleSubmit()` --calls--> `updateBooking()`  [INFERRED]
  src/views/bookings/BookingCreateView.vue → src/api/booking.js
- `searchCities()` --calls--> `fetchCities()`  [INFERRED]
  src/views/bookings/BookingCreateView.vue → src/api/city.js
- `searchCities()` --calls--> `fetchCities()`  [INFERRED]
  src/views/bookings/BookingDetailView.vue → src/api/city.js
- `searchCities()` --calls--> `fetchCities()`  [INFERRED]
  src/views/bookings/BookingListView.vue → src/api/city.js

## Hyperedges (group relationships)
- **Frontend App Bootstrap** — index_frontend_html_entrypoint, index_app_mount_element, index_main_js_module [INFERRED 0.85]
- **Vue Vite Developer Guidance** — readme_vue_3_vite_template, readme_script_setup_sfcs, readme_vue_ide_support [EXTRACTED 1.00]
- **Capacitor SPM Dependency Hosting** — capapp_spm_package, capapp_spm_dependencies, capapp_capacitor_project [EXTRACTED 1.00]
- **Android Splash Shared Visual Identity** — splash_default_landscape_mdpi, splash_land_hdpi, splash_land_mdpi, splash_land_xhdpi, splash_land_xxhdpi, splash_land_xxxhdpi, splash_port_hdpi, splash_port_mdpi, splash_port_xhdpi, splash_port_xxhdpi, splash_port_xxxhdpi, splash_centered_blue_mark_white_background [EXTRACTED 1.00]
- **Android Landscape Splash Density Set** — splash_land_hdpi, splash_land_mdpi, splash_land_xhdpi, splash_land_xxhdpi, splash_land_xxxhdpi [EXTRACTED 1.00]
- **Android Portrait Splash Density Set** — splash_port_hdpi, splash_port_mdpi, splash_port_xhdpi, splash_port_xxhdpi, splash_port_xxxhdpi [EXTRACTED 1.00]
- **Android Launcher Icon Variants** — ic_launcher_standard_launcher_icon, ic_launcher_foreground_adaptive_launcher_artwork, ic_launcher_round_launcher_icon [EXTRACTED 1.00]
- **Android Launcher Density Buckets** — mipmap_mdpi_launcher_density_set, mipmap_hdpi_launcher_density_set, mipmap_xhdpi_launcher_density_set, mipmap_xxhdpi_launcher_density_set, mipmap_xxxhdpi_launcher_density_set [EXTRACTED 1.00]
- **Launcher Visual Identity** — launcher_icon_blue_geometric_mark, launcher_icon_white_grid_background, ic_launcher_standard_launcher_icon, ic_launcher_round_launcher_icon, ic_launcher_foreground_adaptive_launcher_artwork [INFERRED 0.95]
- **iOS Branding Asset Set** — appicon_512_2x_drent_app_icon, splash_2732_1_drent_splash_screen, splash_2732_2_drent_splash_screen, splash_2732_base_drent_splash_screen, drent_blue_angular_logo_mark [INFERRED 0.85]
- **Frontend Source Visual Assets** — frontend_src_asset_bundle, fuel_gauge_vehicle_fuel_level_reference, hero_layered_purple_platforms, vite_vite_logo_parenthesized, vue_vue_logo [EXTRACTED 1.00]
- **Vue Vite Scaffold Identity Assets** — frontend_vue_vite_scaffold_assets, favicon_vite_lightning_mark, vite_vite_logo_parenthesized, vue_vue_logo [INFERRED 0.85]
- **Four View Vehicle Diagram Assets** — car_front_front_view_vehicle_diagram, car_back_rear_view_vehicle_diagram, car_left_left_side_vehicle_diagram, car_right_right_side_vehicle_diagram [EXTRACTED 1.00]
- **Physical Check Body Map Views** — car_front_front_view_vehicle_diagram, car_back_rear_view_vehicle_diagram, car_left_left_side_vehicle_diagram, car_right_right_side_vehicle_diagram, car_assets_physical_check_body_diagram_ui [INFERRED 0.85]
- **App Branding Assets** — public_favicon, public_icon_dark, public_icon_light, public_icons, public_logo_dark, public_logo_light [INFERRED 0.85]

## Communities (144 total, 24 thin omitted)

### Community 0 - "Booking Detail Management"
Cohesion: 0.02
Nodes (81): activeDetails, activePayments, additionalCostForm, additionalTypeOptions, batalForm, billableDetails, bookingCalculatedTagihan, bookingInvoice (+73 more)

### Community 1 - "Booking List & Calendar"
Cohesion: 0.04
Nodes (27): activeStatusOptions, baseCalendarUnits, bookingContextMenu, calendarOwnerOptions, calendarUnits, closedStatusOptions, closedTabStatusValues, contextMenuItems (+19 more)

### Community 2 - "Receivable & Invoice"
Cohesion: 0.05
Nodes (24): refreshInvoiceAmount(), printPayment(), bookingPaymentId(), canRequestVoidPayment(), getInvoicePublicUrl(), openInvoiceView(), openPaymentDialog(), openPaymentFromReceivable() (+16 more)

### Community 3 - "Rent-to-Rent Finance"
Cohesion: 0.05
Nodes (14): canGenerateBill, computedSummary, debtGroups, ownerOptions, paymentAccountOptions, paymentPreviewItems, paymentTarget, remainingAfterPayment (+6 more)

### Community 4 - "Package Dependencies"
Cohesion: 0.06
Nodes (35): dependencies, autoprefixer, axios, @capacitor/android, @capacitor/core, @capacitor/ios, date-fns, pinia (+27 more)

### Community 5 - "Physical Check Form"
Cohesion: 0.06
Nodes (23): activeDetail, activeGalleryPhoto, annotatorCanvas, annotatorPhoto, annotatorSource, annotatorVisible, bookingId, canSubmit (+15 more)

### Community 6 - "Dashboard View"
Cohesion: 0.07
Nodes (19): activeBookingTab, activeCashflowTab, activeLeaderboard, activeLeaderboardStatus, alerts, armadaStatus, bookingsPlaceholderCount, bookingsWithUnitCount (+11 more)

### Community 7 - "Booking Creation"
Cohesion: 0.08
Nodes (11): cityOptions, clearUnitCityFilter(), customerOptions, date, formatCurrency(), mapPricingPackageOption(), onKotaChange(), searchUnits() (+3 more)

### Community 9 - "App Layout & Navigation"
Cohesion: 0.10
Nodes (11): isMenuItemActive(), menuSections, normalizePath(), userInitials, userPhotoUrl, usePermission(), auth, router (+3 more)

### Community 11 - "Customer Management"
Cohesion: 0.10
Nodes (10): canDelete, detailTimeline, end, fetchData(), hasRiskCustomer, onPageChange(), onSearch(), rentalHistory (+2 more)

### Community 13 - "Operational Cost View"
Cohesion: 0.09
Nodes (19): codeCompare, costs, costType, dateA, dateB, deposits, detailIds, detailRows (+11 more)

### Community 14 - "Payment Account Master"
Cohesion: 0.10
Nodes (13): activeAccountCount, adjustForm, canAdjust, form, formatCurrency(), formErrors, isMobile, saving (+5 more)

### Community 15 - "Booking Detail Actions"
Cohesion: 0.13
Nodes (20): addAdditionalCost(), updateBooking(), extend(), requestPhysicalCheck(), doSubmitDetail(), formatLocalDateTime(), loadBooking(), requestPhysicalCheckFromBooking() (+12 more)

### Community 16 - "Public Invoice View"
Cohesion: 0.10
Nodes (13): authorizedSignName, branchContactItems, branchLogoUrl, customerAddressLines, customerContactLines, customerName, filteredPaymentAccounts, hasTermsAndConditions (+5 more)

### Community 17 - "Unit API"
Cohesion: 0.18
Nodes (15): batchUpdateUnitCity(), createUnit(), deleteUnit(), deleteUnitPhoto(), getUnit(), getUnits(), updateUnit(), uploadUnitPhoto() (+7 more)

### Community 18 - "Driver API"
Cohesion: 0.15
Nodes (10): createDriver(), deleteDriver(), fetchDrivers(), updateDriver(), updateDriverBalance(), searchDrivers(), searchDrivers(), driverOptions (+2 more)

### Community 19 - "Driver Operational View"
Cohesion: 0.14
Nodes (7): activeFunds, handleAcceptFund(), pastSchedules, rejectedExpenses, reload(), submitDriverExpense(), upcomingSchedules

### Community 20 - "Branch Form Dialog"
Cohesion: 0.12
Nodes (7): currentLogoUrl, form, formErrors, logoInput, logoPreview, selectedLogo, shouldRemoveLogo

### Community 22 - "Booking Rental Duration"
Cohesion: 0.17
Nodes (15): addRentalDuration(), applyDefaultTime(), applyRollingOldDetail(), cloneDetailCosts(), getExtendStartDate(), getNextRentalStartDate(), onRollingDetailChange(), openExtendDialog() (+7 more)

### Community 23 - "Capacitor App Config"
Cohesion: 0.17
Nodes (15): Capacitor Project, Do Not Modify CapApp-SPM Contents, SPM Dependencies, CapApp-SPM Package, CapApp-SPM README, App Mount Element, Favicon SVG, Frontend HTML Entrypoint (+7 more)

### Community 24 - "Android Splash Screens"
Cohesion: 0.30
Nodes (15): Android Density Scale Splash Variants, Android Landscape Splash Variants, Android Portrait Splash Variants, Centered Blue Geometric Mark on White Splash Background, Default Android Splash PNG, Landscape HDPI Android Splash PNG, Landscape MDPI Android Splash PNG, Landscape XHDPI Android Splash PNG (+7 more)

### Community 25 - "Branch List View"
Cohesion: 0.20
Nodes (6): canCreate, errorMessage(), onDialogSubmit(), onPageChange(), refresh(), userBranchId

### Community 27 - "Booking List Helpers"
Cohesion: 0.20
Nodes (12): formatPackage(), getDisplayDetail(), getDriverInfo(), getEarliestDate(), getLateInfo(), getLatestDate(), getPeriodEndDate(), getPeriodStartDate() (+4 more)

### Community 29 - "Supervisor Requests"
Cohesion: 0.17
Nodes (4): approveRevertOperational(), rejectRevertOperational(), approveRequest(), submitReject()

### Community 30 - "Booking List Filters"
Cohesion: 0.24
Nodes (11): applyFilters(), getActiveTabStatusFilter(), getClosedTabStatusFilter(), isStatusSelected(), loadCalendarData(), loadData(), nextMonth(), onCityChange() (+3 more)

### Community 31 - "City API"
Cohesion: 0.29
Nodes (6): createCity(), deleteCity(), fetchCities(), updateCity(), searchCities(), searchCities()

### Community 32 - "Member API"
Cohesion: 0.24
Nodes (3): get(), getExtensions(), list()

### Community 33 - "iOS App Delegate"
Cohesion: 0.20
Nodes (3): AppDelegate, UIApplicationDelegate, UIResponder

### Community 34 - "User API"
Cohesion: 0.33
Nodes (6): createUser(), deleteUser(), getRoles(), getUsers(), resetUserPassword(), updateUser()

### Community 35 - "Android Launcher Icons"
Cohesion: 0.36
Nodes (10): Adaptive Launcher Foreground Artwork, Round Android Launcher Icon, Standard Android Launcher Icon, Blue Geometric Launcher Mark, White Diagonal Grid Background, HDPI Launcher Icon Density Set, MDPI Launcher Icon Density Set, XHDPI Launcher Icon Density Set (+2 more)

### Community 36 - "Customer API"
Cohesion: 0.36
Nodes (6): createCustomer(), deleteCustomer(), fetchCustomer(), fetchCustomers(), updateCustomer(), searchCustomers()

### Community 37 - "City List View"
Cohesion: 0.22
Nodes (5): canDelete, form, formErrors, saving, showDialog

### Community 38 - "Booking Unit Selection"
Cohesion: 0.22
Nodes (9): clearUnitCityFilter(), getCityIdByName(), getInitialBookingDetail(), getUnitAllInByPaket(), getUnitHargaByPaket(), onKotaChange(), openDetailDialog(), openPrimaryUnitDialog() (+1 more)

### Community 39 - "Transaction Report"
Cohesion: 0.22
Nodes (8): bookingRevenue, cleaned, current, map, merged, q, rentalIncome, seenIndices

### Community 40 - "Branch API"
Cohesion: 0.43
Nodes (5): createBranch(), deleteBranch(), fetchBranch(), fetchBranches(), updateBranch()

### Community 42 - "Pricing Package API"
Cohesion: 0.39
Nodes (4): createPricingPackage(), deletePricingPackage(), getPricingPackages(), updatePricingPackage()

### Community 43 - "Booking Cost Totals"
Cohesion: 0.32
Nodes (8): detailBillableCostTotal(), detailConsumerBill(), detailCostTotal(), detailRentalSubtotal(), detailUnitPriceTotal(), detailUnitTotalWithCosts(), getBillableCostTotal(), sumCosts()

### Community 44 - "Rental Owner API"
Cohesion: 0.39
Nodes (4): createRentalOwner(), deleteRentalOwner(), getRentalOwners(), updateRentalOwner()

### Community 45 - "Cost Type API"
Cohesion: 0.39
Nodes (4): createCostType(), deleteCostType(), getCostTypes(), updateCostType()

### Community 46 - "Payment Account API"
Cohesion: 0.39
Nodes (4): createPaymentAccount(), deletePaymentAccount(), getPaymentAccounts(), updatePaymentAccount()

### Community 47 - "Booking Search Filters"
Cohesion: 0.25
Nodes (8): debounceSearch(), onAccountFilter(), onCityFilter(), onCostTypeFilter(), onCustomerFilter(), onDriverFilter(), onPricingPackageFilter(), onUnitFilter()

### Community 48 - "Booking Package Pricing"
Cohesion: 0.25
Nodes (8): applyWaitingPackage(), createAllInPackageCost(), getUnitAllInByPaket(), getWaitingRentalDuration(), onWaitingCostAmountUpdate(), syncWaitingAllInOperationalCosts(), packageCostItems(), syncAllInOperationalCosts()

### Community 50 - "Entity CRUD Operations"
Cohesion: 0.29
Nodes (5): update(), saveCustomer(), save(), save(), if()

### Community 51 - "Booking Price Display"
Cohesion: 0.29
Nodes (7): approveVoidRequest(), detailPricingModeLabel(), detailUnitPriceDescription(), formatCurrency(), formatSignedCostAmount(), getSignedCostAmount(), submitPayment()

### Community 52 - "Booking Detail Filters"
Cohesion: 0.29
Nodes (7): debounceSearch(), onAccountFilter(), onCityFilter(), onCostTypeFilter(), onDriverFilter(), onPricingPackageFilter(), onUnitFilter()

### Community 54 - "Public Brand Assets"
Cohesion: 0.29
Nodes (7): Favicon, Icon Icon Dark, Icon Icon Light, Icon Icons, Logo Logo, Logo Logo Dark, Logo Logo Light

### Community 55 - "iOS App Icons"
Cohesion: 0.57
Nodes (7): DRENT iOS App Icon 512@2x, iOS AppIcon Asset Set, DRENT Blue Angular Logo Mark, DRENT Splash Screen Variant 1, DRENT Splash Screen Variant 2, DRENT Splash Screen Base Variant, iOS Splash Launch Screen Asset Set

### Community 56 - "Booking Calendar Grid"
Cohesion: 0.29
Nodes (6): d, dEnd, dow, dStart, span, startOffset

### Community 57 - "Booking Time Defaults"
Cohesion: 0.48
Nodes (7): addRentalDuration(), applyDefaultTime(), applyDirectWaitingListBookingDefaults(), getDateKey(), setDefaultReturnTime(), setDefaultStartTime(), syncReturnDateFromDuration()

### Community 58 - "Physical Check Photos"
Cohesion: 0.29
Nodes (7): compressImage(), logFillActivity(), onPhotoSelect(), removePhoto(), saveAnnotation(), sendOtp(), setFuelMarker()

### Community 59 - "Vehicle Diagram Assets"
Cohesion: 0.73
Nodes (6): Four-View Vehicle Diagram Asset Set, Vehicle Physical-Check Body Diagram UI, Car Back Rear View SVG, Car Front View SVG, Car Left Side View SVG, Car Right Side View SVG

### Community 60 - "Frontend Asset Bundle"
Cohesion: 0.47
Nodes (6): Frontend Source Asset Bundle, Vue Vite Scaffold Assets, Vehicle Fuel Gauge Reference Image, Layered Purple Hero Graphic, Vite Logo With Parentheses, Vue Logo

### Community 61 - "Booking Submit Flow"
Cohesion: 0.33
Nodes (6): buildWaitingListPayload(), formatDateTime(), handleSubmit(), isInvalidBookingMoney(), validateBookingForm(), validateWaitingListForm()

### Community 65 - "iOS Icon Asset Config"
Cohesion: 0.40
Nodes (4): images, info, author, version

### Community 66 - "Booking Package Change"
Cohesion: 0.40
Nodes (5): applyPricingPackage(), onDetailPackageChange(), onExtendPackageChange(), onRollingNewPackageChange(), onRollingOldPackageChange()

### Community 67 - "iOS Splash Config"
Cohesion: 0.40
Nodes (4): images, info, author, version

### Community 68 - "Physical Check Init"
Cohesion: 0.40
Nodes (5): hydrateExistingCheck(), initializeChecklist(), loadData(), logStepOpened(), resolveMediaUrl()

### Community 72 - "iOS Asset Metadata"
Cohesion: 0.50
Nodes (3): info, author, version

### Community 73 - "Booking Dialog State"
Cohesion: 0.50
Nodes (4): closeDialogSilently(), getDialogStateMap(), onDialogVisibleChange(), requestCloseDialog()

### Community 74 - "Unit Schedule Check"
Cohesion: 0.50
Nodes (4): checkUnitSchedule(), confirmScheduleConflict(), submitDetail(), validateUnitSchedule()

### Community 75 - "Capacitor Config"
Cohesion: 0.50
Nodes (3): appId, appName, webDir

### Community 76 - "R2R Payment Dialog"
Cohesion: 0.67
Nodes (4): ensurePaymentAccounts(), openDebtPaymentDialog(), openDirectPayment(), openPaymentDialog()

### Community 77 - "R2R Public Bill Link"
Cohesion: 0.50
Nodes (4): copyToClipboard(), openPublicBill(), publicBillUrl(), sendBill()

### Community 78 - "Physical Check Submit"
Cohesion: 0.50
Nodes (4): buildPayload(), buildPublicSignaturePayload(), submit(), validateForm()

### Community 81 - "Booking Unit Change"
Cohesion: 0.67
Nodes (3): onRollingOldUnitChange(), onUnitChange(), onUnitSelect()

### Community 83 - "Booking Cost Subtotals"
Cohesion: 0.67
Nodes (3): getDetailCostTotal(), getDetailRentalSubtotal(), getDetailTotalSewa()

### Community 84 - "Booking City Search"
Cohesion: 0.67
Nodes (3): loadFilterOptions(), onCityDropdownShow(), searchCities()

### Community 86 - "Photo Annotator"
Cohesion: 0.67
Nodes (3): drawAnnotatorImage(), openAnnotator(), resetAnnotation()

### Community 87 - "Annotator Canvas"
Cohesion: 0.67
Nodes (3): canvasPoint(), moveAnnotating(), startAnnotating()

### Community 88 - "Physical Check Steps"
Cohesion: 0.67
Nodes (3): goToStep(), nextStep(), validateStep()

## Knowledge Gaps
- **329 isolated node(s):** `appId`, `appName`, `webDir`, `name`, `private` (+324 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **24 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `fetchCities()` connect `City API` to `Booking City Search`?**
  _High betweenness centrality (0.101) - this node is a cross-community bridge._
- **Why does `searchCities()` connect `Booking City Search` to `Booking List & Calendar`, `City API`?**
  _High betweenness centrality (0.090) - this node is a cross-community bridge._
- **Why does `update()` connect `Entity CRUD Operations` to `Member API`, `Branch List View`, `Pricing Package View`?**
  _High betweenness centrality (0.052) - this node is a cross-community bridge._
- **What connects `appId`, `appName`, `webDir` to the rest of the system?**
  _330 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Booking Detail Management` be split into smaller, more focused modules?**
  _Cohesion score 0.017699115044247787 - nodes in this community are weakly interconnected._
- **Should `Booking List & Calendar` be split into smaller, more focused modules?**
  _Cohesion score 0.04081632653061224 - nodes in this community are weakly interconnected._
- **Should `Receivable & Invoice` be split into smaller, more focused modules?**
  _Cohesion score 0.05319148936170213 - nodes in this community are weakly interconnected._