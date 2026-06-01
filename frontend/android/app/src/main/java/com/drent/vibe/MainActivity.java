package com.drent.vibe;

import android.os.Bundle;
import android.os.SystemClock;
import androidx.core.splashscreen.SplashScreen;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        SplashScreen splashScreen = SplashScreen.installSplashScreen(this);

        long startTime = SystemClock.elapsedRealtime();
        splashScreen.setKeepOnScreenCondition(
            () -> SystemClock.elapsedRealtime() - startTime < 800
        );

        super.onCreate(savedInstanceState);
    }
}
